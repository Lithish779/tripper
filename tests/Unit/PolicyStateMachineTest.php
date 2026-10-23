<?php

namespace Tests\Unit;

use App\Enums\PolicyStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\PolicyRequest;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected PolicyStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new PolicyStateMachine();
    }

    public function test_allows_valid_linear_transitions(): void
    {
        $policy = PolicyRequest::create([
            'agent_id' => 'AGENT-01',
            'client_request_id' => 'REQ-TEST-01',
            'customer_name' => 'Alice',
            'date_of_birth' => '1995-05-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => 5,
            'base_daily_rate' => 10.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.00,
            'premium' => 50.00,
            'status' => PolicyStatus::RECEIVED,
        ]);

        // RECEIVED -> VALIDATED
        $this->stateMachine->transition($policy, PolicyStatus::VALIDATED, 'Validation passed');
        $this->assertSame(PolicyStatus::VALIDATED, $policy->fresh()->status);

        // VALIDATED -> SUBMITTED
        $this->stateMachine->transition($policy, PolicyStatus::SUBMITTED, 'Submitted to insurer');
        $this->assertSame(PolicyStatus::SUBMITTED, $policy->fresh()->status);

        // SUBMITTED -> ISSUED
        $this->stateMachine->transition($policy, PolicyStatus::ISSUED, 'Insurer accepted');
        $this->assertSame(PolicyStatus::ISSUED, $policy->fresh()->status);

        // Audit trail records created
        $this->assertCount(3, $policy->stateHistories);
    }

    public function test_rejects_regression_from_issued_to_submitted_or_failed(): void
    {
        $policy = PolicyRequest::create([
            'agent_id' => 'AGENT-01',
            'client_request_id' => 'REQ-TEST-02',
            'customer_name' => 'Bob',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => 5,
            'base_daily_rate' => 10.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.00,
            'premium' => 50.00,
            'status' => PolicyStatus::ISSUED,
            'policy_number' => 'POL-TEST-1234',
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage("Invalid state regression/transition from 'ISSUED' to 'SUBMITTED'");

        $this->stateMachine->transition($policy, PolicyStatus::SUBMITTED, 'Illegal retry attempt');
    }
}
