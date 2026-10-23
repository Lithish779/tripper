<?php

namespace App\Services\StateMachine;

use App\Enums\PolicyStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Support\Facades\DB;

class PolicyStateMachine
{
    /**
     * Transition a policy request to a new status.
     * Rejects invalid regressions and persists state history.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(
        PolicyRequest $policy,
        PolicyStatus $toStatus,
        string $reason = 'State transition',
        array $metadata = []
    ): PolicyRequest {
        $fromStatus = $policy->status;

        // If already in target status, return idempotently without duplicate audit record
        if ($fromStatus === $toStatus) {
            return $policy;
        }

        if (! $fromStatus->canTransitionTo($toStatus)) {
            throw new InvalidStateTransitionException(
                "Invalid state regression/transition from '{$fromStatus->value}' to '{$toStatus->value}'. Reason: {$reason}"
            );
        }

        return DB::transaction(function () use ($policy, $fromStatus, $toStatus, $reason, $metadata) {
            $policy->status = $toStatus;
            $policy->save();

            PolicyStateHistory::create([
                'policy_request_id' => $policy->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            return $policy;
        });
    }
}
