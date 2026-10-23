<?php

namespace Database\Seeders;

use App\Enums\PolicyStatus;
use App\Models\IssuanceAttempt;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PolicyFlowDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Confirmed ISSUED policy (Matching prompt example)
        $policy1 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'travel_days' => 5,
            'base_daily_rate' => 20.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.80,
            'coverage_multiplier' => 1.25,
            'premium' => 225.00,
            'status' => PolicyStatus::ISSUED,
            'policy_number' => 'POL-INS-84920194',
            'provider_reference' => 'REF-INS-9A8B7C6D5E',
            'attempts_count' => 1,
            'metadata' => ['calculated_age' => 28, 'source' => 'Seed'],
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $policy1->id,
            'from_status' => null,
            'to_status' => PolicyStatus::RECEIVED,
            'reason' => 'Intake accepted',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy1->id,
            'from_status' => PolicyStatus::RECEIVED,
            'to_status' => PolicyStatus::VALIDATED,
            'reason' => 'Validation rules and pricing parameters passed',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy1->id,
            'from_status' => PolicyStatus::VALIDATED,
            'to_status' => PolicyStatus::SUBMITTED,
            'reason' => 'Submitting request to insurer gateway',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy1->id,
            'from_status' => PolicyStatus::SUBMITTED,
            'to_status' => PolicyStatus::ISSUED,
            'reason' => 'Policy issuance confirmed by insurer',
            'metadata' => ['policy_number' => 'POL-INS-84920194'],
        ]);

        IssuanceAttempt::create([
            'policy_request_id' => $policy1->id,
            'attempt_number' => 1,
            'idempotency_key' => 'IDEMP-AGENT-17-AGT-2026-0001-1',
            'request_payload' => ['client_request_id' => 'AGT-2026-0001'],
            'response_payload' => ['status' => 'ISSUED', 'policy_number' => 'POL-INS-84920194'],
            'http_status' => 200,
            'outcome' => 'SUCCESS',
        ]);

        MockInsurerPolicy::create([
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'policy_number' => 'POL-INS-84920194',
            'provider_reference' => 'REF-INS-9A8B7C6D5E',
            'status' => 'ISSUED',
            'premium' => 225.00,
            'issued_at' => Carbon::now(),
        ]);

        // 2. Policy in PENDING_CONFIRMATION (Timeout after success scenario ready for reconciliation)
        $policy2 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sarah Connor',
            'date_of_birth' => '1985-02-14',
            'destination' => 'SCHENGEN',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-10',
            'plan_code' => 'COMPREHENSIVE',
            'sum_insured' => 500000,
            'travel_days' => 10,
            'base_daily_rate' => 35.00,
            'age_multiplier' => 1.20,
            'destination_multiplier' => 1.50,
            'coverage_multiplier' => 1.50,
            'premium' => 945.00,
            'status' => PolicyStatus::PENDING_CONFIRMATION,
            'attempts_count' => 1,
            'last_error' => 'Gateway timeout: insurer did not acknowledge response within timeout threshold.',
            'metadata' => ['calculated_age' => 41, 'source' => 'Seed'],
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $policy2->id,
            'from_status' => null,
            'to_status' => PolicyStatus::RECEIVED,
            'reason' => 'Intake accepted',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy2->id,
            'from_status' => PolicyStatus::RECEIVED,
            'to_status' => PolicyStatus::VALIDATED,
            'reason' => 'Validation rules and pricing parameters passed',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy2->id,
            'from_status' => PolicyStatus::VALIDATED,
            'to_status' => PolicyStatus::SUBMITTED,
            'reason' => 'Submitting request to insurer gateway',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy2->id,
            'from_status' => PolicyStatus::SUBMITTED,
            'to_status' => PolicyStatus::PENDING_CONFIRMATION,
            'reason' => 'Timeout received from insurer; marked for safe reconciliation rather than re-submitting blindly',
        ]);

        IssuanceAttempt::create([
            'policy_request_id' => $policy2->id,
            'attempt_number' => 1,
            'idempotency_key' => 'IDEMP-AGENT-17-AGT-2026-0002-1',
            'request_payload' => ['client_request_id' => 'AGT-2026-0002'],
            'response_payload' => ['message' => 'Gateway timeout'],
            'http_status' => 504,
            'outcome' => 'TIMEOUT',
            'error_message' => 'Gateway timeout: insurer did not acknowledge response within timeout threshold.',
        ]);

        // Insurer did issue policy internally!
        MockInsurerPolicy::create([
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sarah Connor',
            'policy_number' => 'POL-INS-33A4B5C6',
            'provider_reference' => 'REF-INS-SC-9900',
            'status' => 'ISSUED',
            'premium' => 945.00,
            'issued_at' => Carbon::now(),
        ]);

        // 3. Policy in FAILED (Temporary failure ready for retry)
        $policy3 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-25',
            'customer_name' => 'Arthur Dent',
            'date_of_birth' => '1978-03-11',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-20',
            'trip_end' => '2026-11-25',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => 6,
            'base_daily_rate' => 10.00,
            'age_multiplier' => 1.20,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.00,
            'premium' => 72.00,
            'status' => PolicyStatus::FAILED,
            'attempts_count' => 1,
            'last_error' => 'Underwriting gateway temporarily overloaded, retry recommended.',
            'metadata' => ['calculated_age' => 48, 'source' => 'Seed'],
        ]);

        PolicyStateHistory::create([
            'policy_request_id' => $policy3->id,
            'from_status' => null,
            'to_status' => PolicyStatus::RECEIVED,
            'reason' => 'Intake accepted',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy3->id,
            'from_status' => PolicyStatus::RECEIVED,
            'to_status' => PolicyStatus::VALIDATED,
            'reason' => 'Validation rules and pricing parameters passed',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy3->id,
            'from_status' => PolicyStatus::VALIDATED,
            'to_status' => PolicyStatus::SUBMITTED,
            'reason' => 'Submitting request to insurer gateway',
        ]);
        PolicyStateHistory::create([
            'policy_request_id' => $policy3->id,
            'from_status' => PolicyStatus::SUBMITTED,
            'to_status' => PolicyStatus::FAILED,
            'reason' => 'Insurer failed request: Underwriting gateway temporarily overloaded, retry recommended.',
        ]);

        IssuanceAttempt::create([
            'policy_request_id' => $policy3->id,
            'attempt_number' => 1,
            'idempotency_key' => 'IDEMP-AGENT-25-AGT-2026-0003-1',
            'request_payload' => ['client_request_id' => 'AGT-2026-0003'],
            'response_payload' => ['message' => 'Service Unavailable'],
            'http_status' => 503,
            'outcome' => 'ERROR',
            'error_message' => 'Underwriting gateway temporarily overloaded, retry recommended.',
        ]);
    }
}
