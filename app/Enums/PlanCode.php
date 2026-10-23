<?php

namespace App\Enums;

enum PlanCode: string
{
    case BASIC = 'BASIC';
    case PLUS = 'PLUS';
    case COMPREHENSIVE = 'COMPREHENSIVE';

    public function baseDailyRate(): float
    {
        return match ($this) {
            self::BASIC => 10.00,
            self::PLUS => 20.00,
            self::COMPREHENSIVE => 35.00,
        };
    }
}
