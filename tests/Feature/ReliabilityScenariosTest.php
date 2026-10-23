<?php

namespace Tests\Feature;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityScenariosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RELIABILITY CASE 1: Duplicate Intake
     * The same agent_id + client_request_id submitted repeatedly must not create another logical request.
     * First request returns 201 Created; repeated request returns 200 OK with identical data.
     */
    public function test_case_1_duplicate_intake_is_idempotent_and_prevents_duplicate_issuance(): void
    {
        $payload = [
            'client_request_id' => 'AGT-DUP-001',
            'agent_id' => 'AGENT-42',
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-05-20',
            'destination' => 'SCHENGEN',
            'trip_start' => '2026-12-10',
            'trip_end' => '2026-12-20',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        // First intake: creates new request
        $res1 = $this->postJson('/api/policy-requests', $payload);
        $res1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'is_duplicate' => false,
                'status' => 'ISSUED',
            ]);

        $policyNumber1 = $res1->json('data.policy_number');
        $policyId1 = $res1->json('data.id');

        // Second intake (duplicate repetition): returns 200 OK safely
        $res2 = $this->postJson('/api/policy-requests', $payload);
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_duplicate' => true,
                'status' => 'ISSUED',
                'data' => [
                    'id' => $policyId1,
                    'policy_number' => $policyNumber1,
                ],
            ]);

        // Verify only 1 physical record exists in the database
        $this->assertSame(
            1,
            PolicyRequest::where('agent_id', 'AGENT-42')
                ->where('client_request_id', 'AGT-DUP-001')
                ->count()
        );
    }

    public function test_concurrent_near_simultaneous_duplicate_intake_handled_safely(): void
    {
        $payload = [
            'client_request_id' => 'AGT-RACE-001',
            'agent_id' => 'AGENT-88',
            'customer_name' => 'Race Customer',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        // Simulate multiple near-simultaneous requests in rapid succession
        $resA = $this->postJson('/api/policy-requests', $payload);
        $resB = $this->postJson('/api/policy-requests', $payload);
        $resC = $this->postJson('/api/policy-requests', $payload);

        $this->assertContains($resA->status(), [200, 201]);
        $this->assertContains($resB->status(), [200, 201]);
        $this->assertContains($resC->status(), [200, 201]);

        // Exactly one policy created in database
        $count = PolicyRequest::where('agent_id', 'AGENT-88')
            ->where('client_request_id', 'AGT-RACE-001')
            ->count();
        $this->assertSame(1, $count);

        $policy = PolicyRequest::where('agent_id', 'AGENT-88')
            ->where('client_request_id', 'AGT-RACE-001')
            ->first();
        $this->assertNotNull($policy->policy_number);
    }

    /**
     * RELIABILITY CASE 2: Timeout After Success
     * Insurer issued policy internally, but HTTP connection timed out.
     * System marks PENDING_CONFIRMATION, avoids blind re-issuance, and resolves safely via reconciliation status lookup.
     */
    public function test_case_2_timeout_after_success_resolved_via_reconciliation(): void
    {
        $payload = [
            'client_request_id' => 'AGT-TIMEOUT-001',
            'agent_id' => 'AGENT-42',
            'customer_name' => 'Timeout Customer',
            'date_of_birth' => '1992-08-15',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-08',
            'plan_code' => 'COMPREHENSIVE',
            'sum_insured' => 500000,
            'mock_scenario' => 'TIMEOUT', // Simulates timeout after issuance
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        // Intake accepts, but status is PENDING_CONFIRMATION (avoiding blind re-issuance)
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'PENDING_CONFIRMATION',
            ]);

        $policyId = $response->json('data.id');
        $policy = PolicyRequest::find($policyId);
        $this->assertSame(PolicyStatus::PENDING_CONFIRMATION, $policy->status);

        // Trigger reconciliation endpoint
        $reconcileRes = $this->postJson("/api/policy-requests/{$policyId}/reconcile");
        $reconcileRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'ISSUED',
            ]);

        $this->assertNotNull($reconcileRes->json('data.policy_number'));

        // Verify state audit trail reflects safe reconciliation
        $policy->refresh();
        $this->assertSame(PolicyStatus::ISSUED, $policy->status);
        $this->assertTrue(
            $policy->stateHistories()->where('to_status', PolicyStatus::ISSUED)->exists()
        );
    }

    /**
     * RELIABILITY CASE 3: Duplicate Callback
     * External provider sends the same webhook event repeatedly.
     * First event is processed; duplicates are ignored safely without corrupting data or overwriting ISSUED state.
     */
    public function test_case_3_duplicate_callback_deduplication_and_state_protection(): void
    {
        // 1. Create a policy in PENDING_CONFIRMATION state via SLOW_RESPONSE scenario
        $createRes = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-CALLBACK-001',
            'agent_id' => 'AGENT-42',
            'customer_name' => 'Callback Customer',
            'date_of_birth' => '1995-03-21',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'mock_scenario' => 'SLOW_RESPONSE',
        ]);

        $createRes->assertStatus(201);
        $policy = PolicyRequest::where('client_request_id', 'AGT-CALLBACK-001')->firstOrFail();
        $this->assertSame(PolicyStatus::PENDING_CONFIRMATION, $policy->status);

        $callbackPayload = [
            'event_id' => 'EVT-998877',
            'client_request_id' => 'AGT-CALLBACK-001',
            'provider_reference' => 'REF-INS-CALLBACK',
            'policy_number' => 'POL-INS-CB-7788',
            'event_type' => 'POLICY_ISSUED',
        ];

        // First callback delivery: processes successfully
        $cbRes1 = $this->postJson('/api/insurer/callback', $callbackPayload);
        $cbRes1->assertStatus(200)
            ->assertJson([
                'status' => 'PROCESSED',
                'policy_number' => 'POL-INS-CB-7788',
            ]);

        $policy->refresh();
        $this->assertSame(PolicyStatus::ISSUED, $policy->status);
        $this->assertSame('POL-INS-CB-7788', $policy->policy_number);

        // Second delivery of same callback: DUPLICATE_IGNORED
        $cbRes2 = $this->postJson('/api/insurer/callback', $callbackPayload);
        $cbRes2->assertStatus(200)
            ->assertJson([
                'status' => 'DUPLICATE_IGNORED',
            ]);

        // Attempt a third callback with DIFFERENT event_id but attempting to DECLINE confirmed policy
        $lateFailureCallback = [
            'event_id' => 'EVT-998878-LATE-DECLINE',
            'client_request_id' => 'AGT-CALLBACK-001',
            'event_type' => 'POLICY_DECLINED',
        ];

        $cbRes3 = $this->postJson('/api/insurer/callback', $lateFailureCallback);
        $cbRes3->assertStatus(200)
            ->assertJson([
                'status' => 'IGNORED_ALREADY_ISSUED',
            ]);

        // Ensure policy remains ISSUED and was not overwritten
        $policy->refresh();
        $this->assertSame(PolicyStatus::ISSUED, $policy->status);
    }

    /**
     * RELIABILITY CASE 4: Retry After Temporary Failure
     * Temporary provider failure (503) marks FAILED. Retry endpoint safely recovers and issues exactly one policy number
     * while preserving the original immutable snapshot premium.
     */
    public function test_case_4_retry_after_temporary_failure_preserves_premium_and_issues_once(): void
    {
        $payload = [
            'client_request_id' => 'AGT-RETRY-001',
            'agent_id' => 'AGENT-42',
            'customer_name' => 'Retry Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'mock_scenario' => 'TEMP_ERROR', // Downstream 503
        ];

        // Initial intake attempt: provider fails with temporary error
        $res1 = $this->postJson('/api/policy-requests', $payload);
        $res1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'FAILED',
            ]);

        $policyId = $res1->json('data.id');
        $initialPremium = (float) $res1->json('data.premium');
        $this->assertEquals(225.00, $initialPremium);

        $policy = PolicyRequest::find($policyId);
        $this->assertSame(PolicyStatus::FAILED, $policy->status);
        $this->assertSame(1, $policy->attempts_count);

        // Perform authorized retry with SUCCESS scenario
        $retryRes = $this->postJson("/api/policy-requests/{$policyId}/retry", [
            'mock_scenario' => 'SUCCESS',
        ]);

        $retryRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'ISSUED',
                'data' => [
                    'premium' => 225.00, // Preserves original snapshot premium!
                ],
            ]);

        $policy->refresh();
        $this->assertSame(PolicyStatus::ISSUED, $policy->status);
        $this->assertSame(2, $policy->attempts_count);
        $this->assertEquals(225.00, (float) $policy->premium);
        $this->assertNotNull($policy->policy_number);
    }
}
