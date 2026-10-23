<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockInsurerPolicy;
use App\Services\Insurer\MockInsurerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockInsurerController extends Controller
{
    public function __construct(
        protected MockInsurerService $mockInsurer,
    ) {}

    /**
     * POST /api/mock-insurer/issue
     */
    public function issue(Request $request): JsonResponse
    {
        $scenario = $request->header('X-Mock-Scenario', $request->input('mock_scenario'));

        $response = $this->mockInsurer->issue($request->all(), $scenario);

        return response()->json($response->rawPayload ?? $response->toArray(), $response->httpStatus);
    }

    /**
     * GET /api/mock-insurer/policies/{client_request_id}
     */
    public function status(string $clientRequestId): JsonResponse
    {
        $response = $this->mockInsurer->checkStatus($clientRequestId);

        return response()->json($response->rawPayload ?? $response->toArray(), $response->httpStatus);
    }

    /**
     * POST /api/mock-insurer/reset (Dev / Testing only)
     */
    public function reset(): JsonResponse
    {
        MockInsurerPolicy::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Mock insurer state cleared.',
        ]);
    }
}
