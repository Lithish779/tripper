<?php

namespace App\Console\Commands;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Services\PolicyFlowEngine;
use Illuminate\Console\Command;

class ReconcilePoliciesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'policyflow:reconcile {--id= : Optional specific policy request ID to reconcile}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile PENDING_CONFIRMATION and NEEDS_RECONCILIATION policies with insurer';

    /**
     * Execute the console command.
     */
    public function handle(PolicyFlowEngine $engine): int
    {
        $id = $this->option('id');

        $query = PolicyRequest::query();

        if ($id) {
            $query->where('id', $id);
        } else {
            $query->whereIn('status', [
                PolicyStatus::PENDING_CONFIRMATION,
                PolicyStatus::NEEDS_RECONCILIATION,
            ]);
        }

        $policies = $query->get();

        if ($policies->isEmpty()) {
            $this->info('No policies requiring reconciliation found.');

            return self::SUCCESS;
        }

        $this->info("Found {$policies->count()} policy request(s) to reconcile.");

        $reconciled = 0;
        $failed = 0;
        $unchanged = 0;

        foreach ($policies as $policy) {
            $this->line("Reconciling Policy #{$policy->id} [{$policy->client_request_id}], Current Status: {$policy->status->value}...");
            $updated = $engine->reconcile($policy);

            if ($updated->status === PolicyStatus::ISSUED) {
                $this->info(" -> SUCCESS: Reconciled to ISSUED. Policy Number: {$updated->policy_number}");
                $reconciled++;
            } elseif ($updated->status === PolicyStatus::FAILED) {
                $this->warn(" -> FAILED: Insurer confirmed policy failure/rejection.");
                $failed++;
            } else {
                $this->line(" -> UNCHANGED: Remains {$updated->status->value}");
                $unchanged++;
            }
        }

        $this->newLine();
        $this->table(
            ['Total Examined', 'Reconciled (ISSUED)', 'Confirmed Failed', 'Unchanged / Pending'],
            [[$policies->count(), $reconciled, $failed, $unchanged]]
        );

        return self::SUCCESS;
    }
}
