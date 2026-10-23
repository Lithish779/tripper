<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntakeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_produces_expected_premium_and_status(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'is_duplicate' => false,
                'status' => 'ISSUED',
                'data' => [
                    'client_request_id' => 'AGT-2026-0001',
                    'agent_id' => 'AGENT-17',
                    'premium' => 225.00,
                    'travel_days' => 5,
                    'status' => 'ISSUED',
                ],
            ]);

        $this->assertNotNull($response->json('data.policy_number'));
        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'premium' => 225.00,
            'status' => 'ISSUED',
        ]);
    }

    public function test_invalid_travel_dates_are_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Invalid Dates',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-14',
            'trip_end' => '2026-11-10', // End before start
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['trip_end'],
            ]);
    }

    public function test_unsupported_plan_and_coverage_are_rejected(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Bad Plan',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'SUPER_PLATINUM', // Unsupported
            'sum_insured' => 999999, // Unsupported
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_code', 'sum_insured']);
    }

    public function test_read_endpoint_returns_current_state_pricing_and_audit_trail(): void
    {
        $createResponse = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0010',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Audit Customer',
            'date_of_birth' => '1995-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-10-01',
            'trip_end' => '2026-10-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);

        $createResponse->assertStatus(201);
        $policyId = $createResponse->json('data.id');

        $readResponse = $this->getJson("/api/policy-requests/{$policyId}");

        $readResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'client_request_id',
                    'status',
                    'policy_number',
                    'premium',
                    'pricing_snapshot' => [
                        'travel_days',
                        'base_daily_rate',
                        'age_multiplier',
                        'destination_multiplier',
                        'coverage_multiplier',
                        'premium',
                        'formula',
                    ],
                    'audit_trail',
                    'issuance_attempts',
                ],
            ]);

        $this->assertNotEmpty($readResponse->json('data.audit_trail'));
        $this->assertNotEmpty($readResponse->json('data.issuance_attempts'));
    }
}
