<?php

namespace App\Services\Insurer;

use App\Models\MockInsurerPolicy;
use Carbon\Carbon;

class MockInsurerService
{
    /**
     * Issue policy against deterministic mock insurer.
     */
    public function issue(array $data, ?string $scenario = null): InsurerResponse
    {
        $scenario = strtoupper($scenario ?: ($data['mock_scenario'] ?? 'SUCCESS'));
        $clientRequestId = $data['client_request_id'];
        $agentId = $data['agent_id'];
        $customerName = $data['customer_name'] ?? 'Sample Customer';
        $premium = (float) ($data['premium'] ?? 0);

        if ($scenario === 'REJECTED') {
            return new InsurerResponse(
                successful: false,
                httpStatus: 422,
                status: 'DECLINED',
                errorMessage: 'Underwriting declined: destination risk threshold exceeded.',
                errorCode: 'UW_DECLINE_HIGH_RISK',
                rawPayload: [
                    'success' => false,
                    'error_code' => 'UW_DECLINE_HIGH_RISK',
                    'message' => 'Underwriting declined: destination risk threshold exceeded.',
                ],
                isTimeout: false,
                isRetryable: false,
            );
        }

        if ($scenario === 'TEMP_ERROR') {
            return new InsurerResponse(
                successful: false,
                httpStatus: 503,
                status: 'SERVICE_UNAVAILABLE',
                errorMessage: 'Underwriting gateway temporarily overloaded, retry recommended.',
                errorCode: 'SERVICE_UNAVAILABLE',
                rawPayload: [
                    'success' => false,
                    'error_code' => 'SERVICE_UNAVAILABLE',
                    'message' => 'Underwriting gateway temporarily overloaded, retry recommended.',
                ],
                isTimeout: false,
                isRetryable: true,
            );
        }

        if ($scenario === 'TIMEOUT') {
            // Real-world scenario: The insurer issued the policy in their internal ledger,
            // but the socket connection dropped / timed out before sending the HTTP 200 back.
            $this->recordPolicy($clientRequestId, $agentId, $customerName, $premium, $data);

            return new InsurerResponse(
                successful: false,
                httpStatus: 504,
                status: 'TIMEOUT',
                errorMessage: 'Gateway timeout: insurer did not acknowledge response within timeout threshold.',
                errorCode: 'GATEWAY_TIMEOUT',
                rawPayload: [
                    'success' => false,
                    'error_code' => 'GATEWAY_TIMEOUT',
                    'message' => 'Gateway timeout: socket read timed out.',
                ],
                isTimeout: true,
                isRetryable: true,
            );
        }

        if ($scenario === 'SLOW_RESPONSE') {
            $providerRef = 'REF-INS-PENDING-' . strtoupper(substr(md5($clientRequestId), 0, 8));

            return new InsurerResponse(
                successful: false,
                httpStatus: 202,
                status: 'PENDING_CONFIRMATION',
                providerReference: $providerRef,
                errorMessage: 'Policy submission queued for asynchronous underwriting.',
                errorCode: 'PENDING_CONFIRMATION',
                rawPayload: [
                    'success' => true,
                    'status' => 'PENDING',
                    'provider_reference' => $providerRef,
                    'message' => 'Policy submission queued for asynchronous underwriting.',
                ],
                isTimeout: false,
                isRetryable: true,
            );
        }

        // Default: SUCCESS
        $insurerPolicy = $this->recordPolicy($clientRequestId, $agentId, $customerName, $premium, $data);

        return new InsurerResponse(
            successful: true,
            httpStatus: 200,
            status: 'ISSUED',
            policyNumber: $insurerPolicy->policy_number,
            providerReference: $insurerPolicy->provider_reference,
            rawPayload: [
                'success' => true,
                'status' => 'ISSUED',
                'policy_number' => $insurerPolicy->policy_number,
                'provider_reference' => $insurerPolicy->provider_reference,
                'premium' => $insurerPolicy->premium,
                'client_request_id' => $clientRequestId,
                'issued_at' => $insurerPolicy->issued_at?->toISOString(),
            ],
            isTimeout: false,
            isRetryable: false,
        );
    }

    /**
     * Check status lookup endpoint for reconciliation.
     */
    public function checkStatus(string $clientRequestId): InsurerResponse
    {
        $policy = MockInsurerPolicy::where('client_request_id', $clientRequestId)->first();

        if (! $policy) {
            return new InsurerResponse(
                successful: false,
                httpStatus: 404,
                status: 'NOT_FOUND',
                errorMessage: "No policy found with client_request_id: '{$clientRequestId}'.",
                errorCode: 'POLICY_NOT_FOUND',
                rawPayload: [
                    'success' => false,
                    'message' => 'Policy record not found at insurer.',
                ],
            );
        }

        return new InsurerResponse(
            successful: $policy->status === 'ISSUED',
            httpStatus: 200,
            status: $policy->status,
            policyNumber: $policy->policy_number,
            providerReference: $policy->provider_reference,
            rawPayload: [
                'success' => true,
                'status' => $policy->status,
                'policy_number' => $policy->policy_number,
                'provider_reference' => $policy->provider_reference,
                'premium' => $policy->premium,
                'client_request_id' => $policy->client_request_id,
                'issued_at' => $policy->issued_at?->toISOString(),
            ],
        );
    }

    /**
     * Create or retrieve deterministic mock insurer policy.
     */
    private function recordPolicy(
        string $clientRequestId,
        string $agentId,
        string $customerName,
        float $premium,
        array $details
    ): MockInsurerPolicy {
        $existing = MockInsurerPolicy::where('client_request_id', $clientRequestId)->first();
        if ($existing) {
            return $existing;
        }

        $hash = strtoupper(substr(md5($clientRequestId), 0, 8));
        $refHash = strtoupper(substr(sha1($clientRequestId), 0, 10));

        return MockInsurerPolicy::create([
            'client_request_id' => $clientRequestId,
            'agent_id' => $agentId,
            'customer_name' => $customerName,
            'policy_number' => "POL-INS-{$hash}",
            'provider_reference' => "REF-INS-{$refHash}",
            'status' => 'ISSUED',
            'premium' => $premium,
            'details' => $details,
            'issued_at' => Carbon::now(),
        ]);
    }
}
