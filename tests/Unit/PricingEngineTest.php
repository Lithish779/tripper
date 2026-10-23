<?php

namespace Tests\Unit;

use App\Exceptions\InvalidPricingParametersException;
use App\Services\Pricing\PricingEngine;
use PHPUnit\Framework\TestCase;

class PricingEngineTest extends TestCase
{
    protected PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PricingEngine();
    }

    /**
     * Test exact example calculation from assessment specification:
     * Plan: PLUS (base 20.00)
     * Travel: 2026-11-10 to 2026-11-14 (5 days)
     * DOB: 1998-04-10 (age 28 -> multiplier 1.00)
     * Destination: INTERNATIONAL (multiplier 1.80)
     * Sum Insured: 300,000 (multiplier 1.25)
     * Premium = 20 × 5 × 1.00 × 1.80 × 1.25 = ₹225.00
     */
    public function test_calculates_exact_sample_specification_premium(): void
    {
        $snapshot = $this->engine->calculate(
            planCode: 'PLUS',
            dob: '1998-04-10',
            destination: 'INTERNATIONAL',
            tripStart: '2026-11-10',
            tripEnd: '2026-11-14',
            sumInsured: 300000
        );

        $this->assertSame(5, $snapshot->travelDays);
        $this->assertSame(28, $snapshot->age);
        $this->assertSame(20.00, $snapshot->baseDailyRate);
        $this->assertSame(1.00, $snapshot->ageMultiplier);
        $this->assertSame(1.80, $snapshot->destinationMultiplier);
        $this->assertSame(1.25, $snapshot->coverageMultiplier);
        $this->assertSame(225.00, $snapshot->premium);
    }

    public function test_age_brackets_and_multipliers(): void
    {
        // Minor (< 18): 0.90
        $minor = $this->engine->calculate('BASIC', '2015-01-01', 'DOMESTIC', '2026-06-01', '2026-06-01', 100000);
        $this->assertSame(0.90, $minor->ageMultiplier);

        // Young adult (18-35): 1.00
        $young = $this->engine->calculate('BASIC', '1995-01-01', 'DOMESTIC', '2026-06-01', '2026-06-01', 100000);
        $this->assertSame(1.00, $young->ageMultiplier);

        // Middle age (36-50): 1.20
        $middle = $this->engine->calculate('BASIC', '1980-01-01', 'DOMESTIC', '2026-06-01', '2026-06-01', 100000);
        $this->assertSame(1.20, $middle->ageMultiplier);

        // Senior (51-65): 1.50
        $senior = $this->engine->calculate('BASIC', '1965-01-01', 'DOMESTIC', '2026-06-01', '2026-06-01', 100000);
        $this->assertSame(1.50, $senior->ageMultiplier);

        // Elder (> 65): 2.00
        $elder = $this->engine->calculate('BASIC', '1950-01-01', 'DOMESTIC', '2026-06-01', '2026-06-01', 100000);
        $this->assertSame(2.00, $elder->ageMultiplier);
    }

    public function test_rejects_invalid_date_logic_trip_end_before_start(): void
    {
        $this->expectException(InvalidPricingParametersException::class);
        $this->expectExceptionMessage('trip_end must be greater than or equal to trip_start.');

        $this->engine->calculate('PLUS', '1990-01-01', 'DOMESTIC', '2026-11-15', '2026-11-10', 300000);
    }

    public function test_rejects_unsupported_plan_code(): void
    {
        $this->expectException(InvalidPricingParametersException::class);
        $this->expectExceptionMessage("Unsupported plan_code: 'GOLD_VIP'");

        $this->engine->calculate('GOLD_VIP', '1990-01-01', 'DOMESTIC', '2026-11-10', '2026-11-14', 300000);
    }

    public function test_rejects_unsupported_destination(): void
    {
        $this->expectException(InvalidPricingParametersException::class);
        $this->expectExceptionMessage("Unsupported destination: 'ANTARCTICA'");

        $this->engine->calculate('PLUS', '1990-01-01', 'ANTARCTICA', '2026-11-10', '2026-11-14', 300000);
    }

    public function test_rejects_unsupported_coverage_limit(): void
    {
        $this->expectException(InvalidPricingParametersException::class);
        $this->expectExceptionMessage("Unsupported sum_insured: '750000'");

        $this->engine->calculate('PLUS', '1990-01-01', 'DOMESTIC', '2026-11-10', '2026-11-14', 750000);
    }
}
