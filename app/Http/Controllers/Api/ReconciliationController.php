<?php

namespace App\Http\Controllers\Api;

use App\Enums\PolicyStatus;
use App\Http\Controllers\Controller;
use App\Models\PolicyRequest;
use App\Services\PolicyFlowEngine;
use Illuminate\Http\JsonResponse;

class ReconciliationController extends Controller
{
    public function __construct(
        protected PolicyFlowEngine $engine,
    ) {}

    /**
     * Run batch reconciliation on unconfirmed requests.
     * POST /api/reconciliation/run
     */
    public function run(): JsonResponse
    {
        $pending = PolicyRequest::whereIn('status', [
            PolicyStatus::PENDING_CONFIRMATION,
            PolicyStatus::NEEDS_RECONCILIATION,
        ])->get();

        $reconciled = 0;
        $stillPending = 0;
        $failed = 0;
        $details = [];

        foreach ($pending as $policy) {
            $prevStatus = $policy->status->value;
            $updated = $this->engine->reconcile($policy);

            if ($updated->status === PolicyStatus::ISSUED) {
                $reconciled++;
            } elseif ($updated->status === PolicyStatus::FAILED) {
                $failed++;
            } else {
                $stillPending++;
            }

            $details[] = [
                'policy_id' => $policy->id,
                'client_request_id' => $policy->client_request_id,
                'previous_status' => $prevStatus,
                'current_status' => $updated->status->value,
                'policy_number' => $updated->policy_number,
            ];
        }

        return response()->json([
            'success' => true,
            'summary' => [
                'total_examined' => $pending->count(),
                'reconciled_to_issued' => $reconciled,
                'failed' => $failed,
                'still_pending' => $stillPending,
            ],
            'details' => $details,
        ], 200);
    }
}
