<?php

namespace App\Services\Pricing;

use App\Enums\CoverageLimit;
use App\Enums\Destination;
use App\Enums\PlanCode;
use App\Exceptions\InvalidPricingParametersException;
use Carbon\Carbon;

class PricingEngine
{
    /**
     * Calculate premium based on deterministic pricing rules.
     *
     * Formula:
     * premium = base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier
     *
     * @throws InvalidPricingParametersException
     */
    public function calculate(
        string $planCode,
        string $dob,
        string $destination,
        string $tripStart,
        string $tripEnd,
        int|float $sumInsured
    ): PricingSnapshot {
        $startDate = Carbon::parse($tripStart)->startOfDay();
        $endDate = Carbon::parse($tripEnd)->startOfDay();
        $birthDate = Carbon::parse($dob)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new InvalidPricingParametersException('trip_end must be greater than or equal to trip_start.');
        }

        if ($birthDate->gt($startDate)) {
            throw new InvalidPricingParametersException('date_of_birth must be before trip_start.');
        }

        // Travel days inclusive
        $travelDays = (int) $startDate->diffInDays($endDate) + 1;

        // Age calculated as of trip_start
        $age = (int) $birthDate->diffInYears($startDate);

        // 1. Base Daily Rate
        $plan = PlanCode::tryFrom(strtoupper($planCode));
        if ($plan === null) {
            throw new InvalidPricingParametersException("Unsupported plan_code: '{$planCode}'. Supported plans: BASIC, PLUS, COMPREHENSIVE.");
        }
        $baseDailyRate = $plan->baseDailyRate();

        // 2. Destination Multiplier
        $dest = Destination::tryFrom(strtoupper($destination));
        if ($dest === null) {
            throw new InvalidPricingParametersException("Unsupported destination: '{$destination}'. Supported: DOMESTIC, SCHENGEN, INTERNATIONAL, US_CANADA.");
        }
        $destinationMultiplier = $dest->multiplier();

        // 3. Coverage Multiplier
        $coverageMultiplier = CoverageLimit::getMultiplier((int) $sumInsured);
        if ($coverageMultiplier === null) {
            throw new InvalidPricingParametersException("Unsupported sum_insured: '{$sumInsured}'. Supported values: 100000, 300000, 500000, 1000000.");
        }

        // 4. Age Multiplier
        $ageMultiplier = $this->calculateAgeMultiplier($age);

        // Calculate final premium rounded to 2 decimals
        $rawPremium = $baseDailyRate * $travelDays * $ageMultiplier * $destinationMultiplier * $coverageMultiplier;
        $premium = round($rawPremium, 2);

        return new PricingSnapshot(
            travelDays: $travelDays,
            age: $age,
            baseDailyRate: $baseDailyRate,
            ageMultiplier: $ageMultiplier,
            destinationMultiplier: $destinationMultiplier,
            coverageMultiplier: $coverageMultiplier,
            premium: $premium,
        );
    }

    private function calculateAgeMultiplier(int $age): float
    {
        return match (true) {
            $age < 18 => 0.90,
            $age <= 35 => 1.00,
            $age <= 50 => 1.20,
            $age <= 65 => 1.50,
            default => 2.00,
        };
    }
}
