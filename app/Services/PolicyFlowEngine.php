<?php

namespace App\Services;

use App\Enums\PolicyStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use App\Services\Insurer\MockInsurerService;
use App\Services\Pricing\PricingEngine;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolicyFlowEngine
{
    public function __construct(
        protected PricingEngine $pricingEngine,
        protected PolicyStateMachine $stateMachine,
        protected MockInsurerService $insurerService,
    ) {}

    /**
     * Intake a policy request atomically.
     * Prevents duplicate requests using DB transactions and unique constraints.
     *
     * @return array{is_new: bool, policy: PolicyRequest}
     */
    public function intake(array $data, ?string $mockScenario = null): array
    {
        $agentId = (string) $data['agent_id'];
        $clientRequestId = (string) $data['client_request_id'];

        // 1. Check if request already exists (Idempotent Fast-Path)
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing) {
            Log::info("Idempotent intake hit for agent: {$agentId}, client_request_id: {$clientRequestId}");

            return [
                'is_new' => false,
                'policy' => $existing,
            ];
        }

        // 2. Compute deterministic premium snapshot
        $pricing = $this->pricingEngine->calculate(
            planCode: $data['plan_code'],
            dob: $data['date_of_birth'],
            destination: $data['destination'],
            tripStart: $data['trip_start'],
            tripEnd: $data['trip_end'],
            sumInsured: $data['sum_insured'],
        );

        // 3. Atomically persist policy request to prevent race conditions
        try {
            $policy = DB::transaction(function () use ($data, $pricing, $agentId, $clientRequestId) {
                $policy = PolicyRequest::create([
                    'agent_id' => $agentId,
                    'client_request_id' => $clientRequestId,
                    'customer_name' => $data['customer_name'],
                    'date_of_birth' => $data['date_of_birth'],
                    'destination' => strtoupper($data['destination']),
                    'trip_start' => $data['trip_start'],
                    'trip_end' => $data['trip_end'],
                    'plan_code' => strtoupper($data['plan_code']),
                    'sum_insured' => (float) $data['sum_insured'],
                    'travel_days' => $pricing->travelDays,
                    'base_daily_rate' => $pricing->baseDailyRate,
                    'age_multiplier' => $pricing->ageMultiplier,
                    'destination_multiplier' => $pricing->destinationMultiplier,
                    'coverage_multiplier' => $pricing->coverageMultiplier,
                    'premium' => $pricing->premium,
                    'status' => PolicyStatus::RECEIVED,
                    'attempts_count' => 0,
                    'metadata' => [
                        'calculated_age' => $pricing->age,
                        'source' => $data['source'] ?? 'API',
                    ],
                ]);

                PolicyStateHistory::create([
                    'policy_request_id' => $policy->id,
                    'from_status' => null,
                    'to_status' => PolicyStatus::RECEIVED,
                    'reason' => 'Policy intake accepted and premium calculated',
                ]);

                // Transition to VALIDATED
                $policy = $this->stateMachine->transition(
                    $policy,
                    PolicyStatus::VALIDATED,
                    'Validation rules and pricing parameters passed'
                );

                return $policy;
            });
        } catch (QueryException $e) {
            // Check if unique key constraint violated due to concurrent near-simultaneous intake
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'uq_agent_client_request')) {
                $existing = PolicyRequest::where('agent_id', $agentId)
                    ->where('client_request_id', $clientRequestId)
                    ->firstOrFail();

                return [
                    'is_new' => false,
                    'policy' => $existing,
                ];
            }

            throw $e;
        }

        // 4. Submit for issuance against the external insurer
        $scenario = $mockScenario ?? ($data['mock_scenario'] ?? null);
        $policy = $this->submitForIssuance($policy, $scenario);

        return [
            'is_new' => true,
            'policy' => $policy,
        ];
    }

    /**
     * Submit policy to external mock insurer with full fault-tolerance.
     */
    public function submitForIssuance(PolicyRequest $policy, ?string $scenario = null): PolicyRequest
    {
        // Guard: Never re-issue an already issued policy
        if ($policy->isIssued()) {
            return $policy;
        }

        // Transition to SUBMITTED
        $policy = $this->stateMachine->transition(
            $policy,
            PolicyStatus::SUBMITTED,
            'Submitting request to insurer gateway'
        );

        $policy->increment('attempts_count');
        $attemptNumber = $policy->attempts_count;
        $idempotencyKey = "IDEMP-{$policy->agent_id}-{$policy->client_request_id}-{$attemptNumber}";

        $submissionPayload = [
            'client_request_id' => $policy->client_request_id,
            'agent_id' => $policy->agent_id,
            'customer_name' => $policy->customer_name,
            'date_of_birth' => $policy->date_of_birth->format('Y-m-d'),
            'destination' => $policy->destination->value,
            'trip_start' => $policy->trip_start->format('Y-m-d'),
            'trip_end' => $policy->trip_end->format('Y-m-d'),
            'plan_code' => $policy->plan_code->value,
            'sum_insured' => $policy->sum_insured,
            'premium' => $policy->premium,
            'idempotency_key' => $idempotencyKey,
            'mock_scenario' => $scenario,
        ];

        // Call Insurer Service
        $response = $this->insurerService->issue($submissionPayload, $scenario);

        // Record Issuance Attempt
        IssuanceAttempt::create([
            'policy_request_id' => $policy->id,
            'attempt_number' => $attemptNumber,
            'idempotency_key' => $idempotencyKey,
            'request_payload' => $submissionPayload,
            'response_payload' => $response->rawPayload,
            'http_status' => $response->httpStatus,
            'outcome' => $response->status ?? ($response->successful ? 'SUCCESS' : 'ERROR'),
            'error_message' => $response->errorMessage,
        ]);

        $policy->last_response = $response->rawPayload;

        if ($response->successful && $response->policyNumber) {
            $policy->policy_number = $response->policyNumber;
            $policy->provider_reference = $response->providerReference;
            $policy->last_error = null;
            $policy->save();

            return $this->stateMachine->transition(
                $policy,
                PolicyStatus::ISSUED,
                'Policy issuance confirmed by insurer',
                ['policy_number' => $response->policyNumber, 'provider_reference' => $response->providerReference]
            );
        }

        if ($response->isTimeout) {
            $policy->last_error = $response->errorMessage;
            $policy->save();

            // Reliability Case 2: Insurer timed out. Mark as PENDING_CONFIRMATION to avoid duplicate issuance
            return $this->stateMachine->transition(
                $policy,
                PolicyStatus::PENDING_CONFIRMATION,
                'Timeout received from insurer; marked for safe reconciliation rather than re-submitting blindly',
                ['error' => $response->errorMessage]
            );
        }

        if ($response->status === 'PENDING_CONFIRMATION') {
            $policy->provider_reference = $response->providerReference;
            $policy->last_error = $response->errorMessage;
            $policy->save();

            return $this->stateMachine->transition(
                $policy,
                PolicyStatus::PENDING_CONFIRMATION,
                'Insurer accepted request for asynchronous processing',
                ['provider_reference' => $response->providerReference]
            );
        }

        // Rejection or failure
        $policy->last_error = $response->errorMessage;
        $policy->save();

        return $this->stateMachine->transition(
            $policy,
            PolicyStatus::FAILED,
            "Insurer failed request: {$response->errorMessage}",
            ['error_code' => $response->errorCode, 'http_status' => $response->httpStatus]
        );
    }

    /**
     * Retry a failed or needs_reconciliation policy request.
     * Preserves original immutable snapshot premium!
     *
     * @throws InvalidStateTransitionException
     */
    public function retry(PolicyRequest $policy, ?string $scenario = 'SUCCESS'): PolicyRequest
    {
        if ($policy->isIssued()) {
            throw new InvalidStateTransitionException('Cannot retry an already ISSUED policy.');
        }

        if (! in_array($policy->status, [PolicyStatus::FAILED, PolicyStatus::NEEDS_RECONCILIATION, PolicyStatus::PENDING_CONFIRMATION], true)) {
            throw new InvalidStateTransitionException("Cannot retry policy in status '{$policy->status->value}'. Must be FAILED, PENDING_CONFIRMATION, or NEEDS_RECONCILIATION.");
        }

        return $this->submitForIssuance($policy, $scenario);
    }

    /**
     * Reconcile a pending or unconfirmed policy by querying provider status lookup.
     */
    public function reconcile(PolicyRequest $policy): PolicyRequest
    {
        if ($policy->isIssued()) {
            return $policy;
        }

        $response = $this->insurerService->checkStatus($policy->client_request_id);

        if ($response->successful && $response->policyNumber) {
            $policy->policy_number = $response->policyNumber;
            $policy->provider_reference = $response->providerReference;
            $policy->last_error = null;
            $policy->last_response = $response->rawPayload;
            $policy->save();

            return $this->stateMachine->transition(
                $policy,
                PolicyStatus::ISSUED,
                'Reconciled via insurer status lookup: confirmed issued policy found',
                ['policy_number' => $response->policyNumber]
            );
        }

        if ($response->httpStatus === 404) {
            // Insurer has no record of issuance
            if ($policy->status === PolicyStatus::PENDING_CONFIRMATION) {
                return $this->stateMachine->transition(
                    $policy,
                    PolicyStatus::NEEDS_RECONCILIATION,
                    'Insurer status lookup returned 404 NOT_FOUND. Needs manual or automated review before retrying.'
                );
            }
        }

        return $policy;
    }
}
