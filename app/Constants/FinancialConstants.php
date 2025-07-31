<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Central repository for financial and business logic constants
 * 
 * This class contains all numeric constants used throughout the application
 * for investment calculations, percentages, and financial conversions.
 */
class FinancialConstants
{
    /**
     * Percentage precision constant for accurate percentage calculations
     * Allows for precision up to 99.9999% (6 decimal places)
     * Example: 50.5555% = 505555 / PERCENTAGE_PRECISION
     */
    public const PERCENTAGE_PRECISION = 1000000;

    /**
     * Percentage divisor for display purposes (4 decimal places)
     * Used to convert internal percentage storage to display format
     * Example: 505555 / PERCENTAGE_DISPLAY_DIVISOR = 50.5555%
     */
    public const PERCENTAGE_DISPLAY_DIVISOR = 10000;

    /**
     * Company commission rate (50%)
     * The percentage of profit that goes to the company
     */
    public const COMPANY_COMMISSION_RATE = 0.5;

    /**
     * Minimum meaningful payment amount in dollars
     * Payments below this threshold are not processed
     */
    public const MINIMUM_PAYMENT_AMOUNT = 0.01;

    /**
     * Cents per dollar conversion constant
     * Used for converting between dollars and cents for MoneyCast
     */
    public const CENTS_PER_DOLLAR = 100;

    /**
     * Days in a year for financial calculations
     * Used for "last year" income calculations
     */
    public const DAYS_IN_YEAR = 365;

    /**
     * Decimal precision for currency formatting
     * Number of decimal places to show for monetary values
     */
    public const DECIMAL_PRECISION = 2;

    /**
     * Chart configuration constants
     */
    public const CHART_TENSION = 0.3;
    public const CHART_MAX_HEIGHT = '300px';
    public const CHART_BORDER_WIDTH = 2;
    public const CHART_COLUMN_SPAN = 12;

    /**
     * Time conversion constants
     */
    public const SECONDS_PER_MINUTE = 60;
    public const MINUTES_PER_HOUR = 60;
    public const HOURS_PER_DAY = 24;
}