<?php

namespace App\Enums;

enum CoverageLimit: int
{
    case TIER_100K = 100000;
    case TIER_300K = 300000;
    case TIER_500K = 500000;
    case TIER_1M = 1000000;

    public function multiplier(): float
    {
        return match ($this) {
            self::TIER_100K => 1.00,
            self::TIER_300K => 1.25,
            self::TIER_500K => 1.50,
            self::TIER_1M => 2.00,
        };
    }

    public static function isSupported(int|float $amount): bool
    {
        return self::tryFrom((int) $amount) !== null;
    }

    public static function getMultiplier(int|float $amount): ?float
    {
        $tier = self::tryFrom((int) $amount);
        return $tier?->multiplier();
    }
}
