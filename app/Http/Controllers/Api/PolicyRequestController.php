<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PolicyIntakeRequest;
use App\Models\PolicyRequest;
use App\Services\PolicyFlowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PolicyRequestController extends Controller
{
    public function __construct(
        protected PolicyFlowEngine $engine,
    ) {}

    /**
     * Intake endpoint: POST /api/policy-requests
     * Returns HTTP 201 for new, HTTP 200 for safe duplicate repetition.
     */
    public function store(PolicyIntakeRequest $request): JsonResponse
    {
        $scenarioHeader = $request->header('X-Mock-Scenario');
        $scenario = $scenarioHeader ?: $request->input('mock_scenario');

        $result = $this->engine->intake($request->validated(), $scenario);

        $policy = $result['policy'];
        $isNew = $result['is_new'];

        return response()->json([
            'success' => true,
            'is_duplicate' => ! $isNew,
            'status' => $policy->status->value,
            'message' => $isNew
                ? 'Policy request accepted and processed.'
                : 'Duplicate intake detected. Returning existing policy request safely without re-issuing.',
            'data' => $this->formatPolicyResponse($policy),
        ], $isNew ? 201 : 200);
    }

    /**
     * Read endpoint: GET /api/policy-requests/{id}
     * Returns policy status, calculated premium breakdown, and audit trail.
     */
    public function show(int|string $id): JsonResponse
    {
        $policy = is_numeric($id)
            ? PolicyRequest::with(['stateHistories', 'attempts'])->find($id)
            : PolicyRequest::with(['stateHistories', 'attempts'])->where('client_request_id', $id)->first();

        if (! $policy) {
            return response()->json([
                'success' => false,
                'message' => "Policy request '{$id}' not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDetailedPolicyResponse($policy),
        ], 200);
    }

    /**
     * List endpoint with filters: GET /api/policy-requests
     */
    public function index(Request $request): JsonResponse
    {
        $query = PolicyRequest::query()->orderBy('created_at', 'desc');

        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        if ($request->has('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->has('client_request_id')) {
            $query->where('client_request_id', $request->query('client_request_id'));
        }

        $policies = $query->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $policies->items(),
            'pagination' => [
                'total' => $policies->total(),
                'current_page' => $policies->currentPage(),
                'last_page' => $policies->lastPage(),
                'per_page' => $policies->perPage(),
            ],
        ], 200);
    }

    /**
     * Retry endpoint: POST /api/policy-requests/{id}/retry
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $policy = PolicyRequest::find($id);

        if (! $policy) {
            return response()->json([
                'success' => false,
                'message' => "Policy request '{$id}' not found.",
            ], 404);
        }

        $scenario = $request->header('X-Mock-Scenario', $request->input('mock_scenario', 'SUCCESS'));

        try {
            $updated = $this->engine->retry($policy, $scenario);

            return response()->json([
                'success' => true,
                'message' => 'Policy retry processed.',
                'status' => $updated->status->value,
                'data' => $this->formatPolicyResponse($updated),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reconcile single request: POST /api/policy-requests/{id}/reconcile
     */
    public function reconcile(int $id): JsonResponse
    {
        $policy = PolicyRequest::find($id);

        if (! $policy) {
            return response()->json([
                'success' => false,
                'message' => "Policy request '{$id}' not found.",
            ], 404);
        }

        $updated = $this->engine->reconcile($policy);

        return response()->json([
            'success' => true,
            'status' => $updated->status->value,
            'message' => "Reconciliation complete. Status is now '{$updated->status->value}'.",
            'data' => $this->formatPolicyResponse($updated),
        ], 200);
    }

    /**
     * Stretch Feature: Bulk CSV intake with row-level validation & duplicate handling
     * POST /api/policy-requests/bulk-csv
     */
    public function bulkCsv(Request $request): JsonResponse
    {
        if (! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'CSV file is required under field name "file".',
            ], 422);
        }

        $file = $request->file('file');
        $csvData = array_map('str_getcsv', file($file->getRealPath()));

        if (count($csvData) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'CSV file is empty or missing headers.',
            ], 422);
        }

        $header = array_map('trim', array_shift($csvData));
        $results = [
            'total_rows' => count($csvData),
            'successful' => 0,
            'duplicates' => 0,
            'failed' => 0,
            'row_reports' => [],
        ];

        foreach ($csvData as $index => $row) {
            $rowNumber = $index + 2;
            if (count($row) !== count($header)) {
                $results['failed']++;
                $results['row_reports'][] = [
                    'row' => $rowNumber,
                    'status' => 'ERROR',
                    'errors' => ['Column count mismatch.'],
                ];
                continue;
            }

            $rowData = array_combine($header, array_map('trim', $row));

            $validator = Validator::make($rowData, [
                'client_request_id' => ['required', 'string'],
                'agent_id' => ['required', 'string'],
                'customer_name' => ['required', 'string'],
                'date_of_birth' => ['required', 'date'],
                'destination' => ['required', 'string', 'in:DOMESTIC,SCHENGEN,INTERNATIONAL,US_CANADA'],
                'trip_start' => ['required', 'date'],
                'trip_end' => ['required', 'date', 'after_or_equal:trip_start'],
                'plan_code' => ['required', 'string', 'in:BASIC,PLUS,COMPREHENSIVE'],
                'sum_insured' => ['required', 'numeric', 'in:100000,300000,500000,1000000'],
            ]);

            if ($validator->fails()) {
                $results['failed']++;
                $results['row_reports'][] = [
                    'row' => $rowNumber,
                    'client_request_id' => $rowData['client_request_id'] ?? null,
                    'status' => 'VALIDATION_FAILED',
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            try {
                $outcome = $this->engine->intake($validator->validated());
                if ($outcome['is_new']) {
                    $results['successful']++;
                    $results['row_reports'][] = [
                        'row' => $rowNumber,
                        'client_request_id' => $rowData['client_request_id'],
                        'status' => 'CREATED',
                        'policy_id' => $outcome['policy']->id,
                        'policy_status' => $outcome['policy']->status->value,
                        'policy_number' => $outcome['policy']->policy_number,
                    ];
                } else {
                    $results['duplicates']++;
                    $results['row_reports'][] = [
                        'row' => $rowNumber,
                        'client_request_id' => $rowData['client_request_id'],
                        'status' => 'DUPLICATE_IGNORED',
                        'policy_id' => $outcome['policy']->id,
                        'policy_status' => $outcome['policy']->status->value,
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['row_reports'][] = [
                    'row' => $rowNumber,
                    'client_request_id' => $rowData['client_request_id'],
                    'status' => 'ERROR',
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'summary' => $results,
        ], 200);
    }

    private function formatPolicyResponse(PolicyRequest $policy): array
    {
        return [
            'id' => $policy->id,
            'client_request_id' => $policy->client_request_id,
            'agent_id' => $policy->agent_id,
            'customer_name' => $policy->customer_name,
            'status' => $policy->status->value,
            'policy_number' => $policy->policy_number,
            'provider_reference' => $policy->provider_reference,
            'premium' => $policy->premium,
            'plan_code' => $policy->plan_code->value,
            'destination' => $policy->destination->value,
            'sum_insured' => $policy->sum_insured,
            'trip_start' => $policy->trip_start->format('Y-m-d'),
            'trip_end' => $policy->trip_end->format('Y-m-d'),
            'travel_days' => $policy->travel_days,
            'attempts_count' => $policy->attempts_count,
            'last_error' => $policy->last_error,
            'created_at' => $policy->created_at?->toISOString(),
            'updated_at' => $policy->updated_at?->toISOString(),
        ];
    }

    private function formatDetailedPolicyResponse(PolicyRequest $policy): array
    {
        $base = $this->formatPolicyResponse($policy);

        $base['pricing_snapshot'] = [
            'travel_days' => $policy->travel_days,
            'base_daily_rate' => $policy->base_daily_rate,
            'age_multiplier' => $policy->age_multiplier,
            'destination_multiplier' => $policy->destination_multiplier,
            'coverage_multiplier' => $policy->coverage_multiplier,
            'premium' => $policy->premium,
            'formula' => 'base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier',
        ];

        $base['audit_trail'] = $policy->stateHistories->map(fn ($history) => [
            'id' => $history->id,
            'from_status' => $history->from_status?->value,
            'to_status' => $history->to_status->value,
            'reason' => $history->reason,
            'metadata' => $history->metadata,
            'created_at' => $history->created_at?->toISOString(),
        ]);

        $base['issuance_attempts'] = $policy->attempts->map(fn ($attempt) => [
            'id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'idempotency_key' => $attempt->idempotency_key,
            'outcome' => $attempt->outcome,
            'http_status' => $attempt->http_status,
            'error_message' => $attempt->error_message,
            'created_at' => $attempt->created_at?->toISOString(),
        ]);

        return $base;
    }
}
