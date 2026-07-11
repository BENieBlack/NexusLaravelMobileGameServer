<?php

namespace LaravelUtilities;

use Carbon\CarbonImmutable;

class ClockUtility
{
    private static ?CarbonImmutable $currentDatetime = null;

    public static function initialize(): void
    {
        if (is_null(self::$currentDatetime)) {
            self::$currentDatetime = CarbonImmutable::now();
            CarbonImmutable::setTestNow(self::$currentDatetime);
        }
    }

    public static function now(): CarbonImmutable
    {
        return self::$currentDatetime ?? CarbonImmutable::now();
    }

    public static function nowToString(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }
}
