<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BulkCsvIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_csv_intake_with_row_level_reporting_and_duplicate_handling(): void
    {
        $csvContent = <<<'EOT'
client_request_id,agent_id,customer_name,date_of_birth,destination,trip_start,trip_end,plan_code,sum_insured
CSV-001,AGENT-99,Customer One,1990-01-01,DOMESTIC,2026-11-01,2026-11-05,BASIC,100000
CSV-002,AGENT-99,Customer Two,1998-04-10,INTERNATIONAL,2026-11-10,2026-11-14,PLUS,300000
CSV-001,AGENT-99,Customer One Duplicate,1990-01-01,DOMESTIC,2026-11-01,2026-11-05,BASIC,100000
CSV-003,AGENT-99,Invalid Dates,1990-01-01,DOMESTIC,2026-11-10,2026-11-05,BASIC,100000
EOT;

        $file = UploadedFile::fake()->createWithContent('policies.csv', $csvContent);

        $response = $this->postJson('/api/policy-requests/bulk-csv', [
            'file' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_rows' => 4,
                    'successful' => 2,
                    'duplicates' => 1,
                    'failed' => 1,
                ],
            ]);
    }
}
