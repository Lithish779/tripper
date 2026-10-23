<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function __construct(
        protected CallbackHandler $handler,
    ) {}

    /**
     * Handle incoming insurer webhook events idempotently.
     * POST /api/insurer/callback
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $result = $this->handler->handle($payload);

        $code = $result['code'] ?? 200;

        return response()->json($result, $code);
    }
}
