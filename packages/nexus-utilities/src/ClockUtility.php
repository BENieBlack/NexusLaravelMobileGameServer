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

    /**
     * テスト用：時刻をリセットする
     */
    public static function reset(): void
    {
        self::$currentDatetime = null;
        CarbonImmutable::setTestNow(null);
    }

    /**
     * テスト用：時刻を指定した値に設定する
     */
    public static function setNow(CarbonImmutable $datetime): void
    {
        self::$currentDatetime = $datetime;
        CarbonImmutable::setTestNow($datetime);
    }
}
