<?php

namespace App\Services\Pricing;

readonly class PricingSnapshot
{
    public function __construct(
        public int $travelDays,
        public int $age,
        public float $baseDailyRate,
        public float $ageMultiplier,
        public float $destinationMultiplier,
        public float $coverageMultiplier,
        public float $premium,
    ) {}

    public function toArray(): array
    {
        return [
            'travel_days' => $this->travelDays,
            'age' => $this->age,
            'base_daily_rate' => $this->baseDailyRate,
            'age_multiplier' => $this->ageMultiplier,
            'destination_multiplier' => $this->destinationMultiplier,
            'coverage_multiplier' => $this->coverageMultiplier,
            'premium' => $this->premium,
        ];
    }
}
