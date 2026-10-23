<?php

namespace App\Services;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\StateMachine\PolicyStateMachine;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackHandler
{
    public function __construct(
        protected PolicyStateMachine $stateMachine,
    ) {}

    /**
     * Process an incoming insurer webhook event idempotently.
     * Prevents duplicate callbacks via unique event_id constraint.
     */
    public function handle(array $payload): array
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        $clientRequestId = (string) ($payload['client_request_id'] ?? '');
        $eventType = strtoupper($payload['event_type'] ?? 'POLICY_ISSUED');

        if (empty($eventId) || empty($clientRequestId)) {
            return [
                'status' => 'REJECTED',
                'message' => 'Missing required fields: event_id and client_request_id are mandatory.',
                'code' => 422,
            ];
        }

        // 1. Fast-path check for existing event
        $existingEvent = ProviderEvent::where('event_id', $eventId)->first();
        if ($existingEvent) {
            Log::info("Duplicate insurer callback ignored for event_id: {$eventId}");

            return [
                'status' => 'DUPLICATE_IGNORED',
                'message' => 'Duplicate provider event ignored. Already processed.',
                'event_id' => $eventId,
                'code' => 200,
            ];
        }

        // 2. Atomically persist event to ensure single execution under concurrent deliveries
        try {
            return DB::transaction(function () use ($eventId, $clientRequestId, $eventType, $payload) {
                $providerEvent = ProviderEvent::create([
                    'event_id' => $eventId,
                    'client_request_id' => $clientRequestId,
                    'provider_reference' => $payload['provider_reference'] ?? null,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'status' => 'PROCESSED',
                    'processed_at' => Carbon::now(),
                ]);

                $policy = PolicyRequest::where('client_request_id', $clientRequestId)->first();
                if (! $policy) {
                    $providerEvent->update(['status' => 'ORPHANED_POLICY_NOT_FOUND']);

                    return [
                        'status' => 'POLICY_NOT_FOUND',
                        'message' => "No policy found matching client_request_id '{$clientRequestId}'.",
                        'code' => 404,
                    ];
                }

                // Reliability Case: Never overwrite or regress an already confirmed ISSUED state
                if ($policy->isIssued()) {
                    return [
                        'status' => 'IGNORED_ALREADY_ISSUED',
                        'message' => 'Policy is already in confirmed ISSUED state. Duplicate callback ignored safely.',
                        'policy_number' => $policy->policy_number,
                        'code' => 200,
                    ];
                }

                if ($eventType === 'POLICY_ISSUED') {
                    $policyNumber = $payload['policy_number'] ?? ('POL-INS-' . strtoupper(substr(md5($clientRequestId), 0, 8)));
                    $policy->policy_number = $policyNumber;
                    $policy->provider_reference = $payload['provider_reference'] ?? $policy->provider_reference;
                    $policy->last_response = $payload;
                    $policy->save();

                    $this->stateMachine->transition(
                        $policy,
                        PolicyStatus::ISSUED,
                        "Async callback from insurer: event_id {$eventId}",
                        ['event_id' => $eventId, 'policy_number' => $policyNumber]
                    );

                    return [
                        'status' => 'PROCESSED',
                        'message' => 'Policy confirmed and marked ISSUED from callback.',
                        'policy_number' => $policyNumber,
                        'code' => 200,
                    ];
                }

                if ($eventType === 'POLICY_DECLINED' || $eventType === 'POLICY_FAILED') {
                    $this->stateMachine->transition(
                        $policy,
                        PolicyStatus::FAILED,
                        "Insurer callback reported decline/failure: event_id {$eventId}",
                        ['event_id' => $eventId, 'reason' => $payload['reason'] ?? 'Declined by insurer']
                    );

                    return [
                        'status' => 'PROCESSED',
                        'message' => 'Policy marked as FAILED based on insurer callback.',
                        'code' => 200,
                    ];
                }

                return [
                    'status' => 'PROCESSED',
                    'message' => "Event {$eventType} recorded successfully.",
                    'code' => 200,
                ];
            });
        } catch (QueryException $e) {
            // Concurrent duplicate callback delivery caught by unique index
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'provider_events_event_id_unique')) {
                return [
                    'status' => 'DUPLICATE_IGNORED',
                    'message' => 'Duplicate provider event detected concurrently and ignored safely.',
                    'event_id' => $eventId,
                    'code' => 200,
                ];
            }

            throw $e;
        }
    }
}
