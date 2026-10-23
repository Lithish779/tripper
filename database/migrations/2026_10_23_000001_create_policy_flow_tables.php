<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('client_request_id');
            $table->string('agent_id');
            $table->string('customer_name');
            $table->date('date_of_birth');
            $table->string('destination');
            $table->date('trip_start');
            $table->date('trip_end');
            $table->string('plan_code');
            $table->decimal('sum_insured', 14, 2);
            $table->integer('travel_days');
            $table->decimal('base_daily_rate', 10, 2);
            $table->decimal('age_multiplier', 6, 2);
            $table->decimal('destination_multiplier', 6, 2);
            $table->decimal('coverage_multiplier', 6, 2);
            $table->decimal('premium', 12, 2);
            $table->string('status')->default('RECEIVED')->index();
            $table->string('policy_number')->nullable()->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedInteger('attempts_count')->default(0);
            $table->text('last_error')->nullable();
            $table->json('last_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Hard database constraint preventing duplicate intake per agent
            $table->unique(['agent_id', 'client_request_id'], 'uq_agent_client_request');
        });

        Schema::create('policy_state_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason')->default('State transition');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'created_at']);
        });

        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('idempotency_key')->index();
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('outcome'); // SUCCESS, TIMEOUT, REJECTED, ERROR
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'attempt_number']);
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique(); // Callback deduplication key
            $table->string('client_request_id')->index();
            $table->string('provider_reference')->nullable()->index();
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('PROCESSED'); // PROCESSED, DUPLICATE_IGNORED, FAILED
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mock_insurer_policies', function (Blueprint $table) {
            $table->id();
            $table->string('client_request_id')->unique();
            $table->string('agent_id');
            $table->string('customer_name');
            $table->string('policy_number')->unique();
            $table->string('provider_reference')->unique();
            $table->string('status'); // ISSUED, DECLINED, PENDING
            $table->decimal('premium', 12, 2);
            $table->json('details')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_insurer_policies');
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('issuance_attempts');
        Schema::dropIfExists('policy_state_histories');
        Schema::dropIfExists('policy_requests');
    }
};
