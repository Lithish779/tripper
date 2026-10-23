<?php

namespace App\Models;

use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Enums\PolicyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PolicyStatus::class,
            'plan_code' => PlanCode::class,
            'destination' => Destination::class,
            'date_of_birth' => 'date:Y-m-d',
            'trip_start' => 'date:Y-m-d',
            'trip_end' => 'date:Y-m-d',
            'sum_insured' => 'float',
            'travel_days' => 'integer',
            'base_daily_rate' => 'float',
            'age_multiplier' => 'float',
            'destination_multiplier' => 'float',
            'coverage_multiplier' => 'float',
            'premium' => 'float',
            'attempts_count' => 'integer',
            'last_response' => 'array',
            'metadata' => 'array',
        ];
    }

    public function stateHistories(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('created_at', 'asc');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class)->orderBy('attempt_number', 'asc');
    }

    public function isIssued(): bool
    {
        return $this->status === PolicyStatus::ISSUED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
