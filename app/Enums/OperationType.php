<?php
// app/Enums/OperationType.php

namespace App\Enums;

enum OperationType: int
{
    case FIRST = 1;
    case BUY_CAR = 2;
    case SELL_CAR = 3;
    case CONTRIB = 4;
    case WITHDRAW = 5;
    case INCOME = 6; // investor income
    case REVENUE = 7; // company revenue
    case C_LEASING = 8;
    case I_LEASING = 9;
    case RECULC = 10;

// Optional: if you need to map the key to a human-readable label
    public function label(): string
    {
        return match ($this) {
            self::FIRST => 'First',
            self::BUY_CAR => 'Buy Car',
            self::SELL_CAR => 'Sell Car',
            self::CONTRIB => 'Contribution',
            self::WITHDRAW => 'Withdraw',
            self::INCOME => 'Income',
            self::REVENUE => 'Revenue',
            self::C_LEASING => 'Car Leasing',
            self::I_LEASING => 'Insurance Leasing',
            self::RECULC => 'Recalculate',
        };
    }

}
