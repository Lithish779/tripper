<?php

namespace App\Enums;

enum Destination: string
{
    case DOMESTIC = 'DOMESTIC';
    case SCHENGEN = 'SCHENGEN';
    case INTERNATIONAL = 'INTERNATIONAL';
    case US_CANADA = 'US_CANADA';

    public function multiplier(): float
    {
        return match ($this) {
            self::DOMESTIC => 1.00,
            self::SCHENGEN => 1.50,
            self::INTERNATIONAL => 1.80,
            self::US_CANADA => 2.20,
        };
    }
}
