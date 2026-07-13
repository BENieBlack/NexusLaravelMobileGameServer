<?php

namespace NexusUtilities;

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
     * 現在時刻と指定日時の秒数差分を計算
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return int 秒数（現在時刻 - 指定日時）
     */
    public static function diffInSeconds(string $dateTimeString): int
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return self::now()->diffInSeconds($targetTime, false);
    }

    /**
     * 現在時刻と指定日時の分数差分を計算
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return int 分数（現在時刻 - 指定日時）
     */
    public static function diffInMinutes(string $dateTimeString): int
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return self::now()->diffInMinutes($targetTime, false);
    }

    /**
     * 現在時刻と指定日時の時間数差分を計算
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return int 時間数（現在時刻 - 指定日時）
     */
    public static function diffInHours(string $dateTimeString): int
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return self::now()->diffInHours($targetTime, false);
    }

    /**
     * 現在時刻と指定日時の日数差分を計算
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return int 日数（現在時刻 - 指定日時）
     */
    public static function diffInDays(string $dateTimeString): int
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return self::now()->diffInDays($targetTime, false);
    }

    /**
     * 指定日時が今日かどうかをチェック
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool
     */
    public static function isToday(string $dateTimeString): bool
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->isToday();
    }

    /**
     * 指定日時の週番号を取得
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return int 週番号（1-53）
     */
    public static function weekOfYear(string $dateTimeString): int
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->weekOfYear;
    }

    /**
     * 指定日時の月を取得
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return int 月（1-12）
     */
    public static function month(string $dateTimeString): int
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->month;
    }

    /**
     * 指定日時の年を取得
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return int 年
     */
    public static function year(string $dateTimeString): int
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->year;
    }

    /**
     * 指定された日時が現在時刻以上かどうかをチェック
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool
     */
    public static function greaterThanOrEqual(string $dateTimeString): bool
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->greaterThanOrEqualTo(self::now());
    }

    /**
     * 指定された日時が現在時刻以下かどうかをチェック
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool
     */
    public static function lessThanOrEqual(string $dateTimeString): bool
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->lessThanOrEqualTo(self::now());
    }

    /**
     * 指定された日時が現在時刻より後かどうかをチェック
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool
     */
    public static function isAfter(string $dateTimeString): bool
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->isAfter(self::now());
    }

    /**
     * 指定された日時が現在時刻より前かどうかをチェック
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool
     */
    public static function isBefore(string $dateTimeString): bool
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return $targetTime->isBefore(self::now());
    }

    /**
     * 日時文字列をCarbonImmutableに変換（外部入力専用）
     * 
     * このメソッドは以下の限定的なケースでのみ使用してください：
     * - Admin画面等の外部入力をCarbonImmutableに変換する場合
     * - DTOの復元（配列/JSONからCarbonImmutableを生成）
     * 
     * 通常のビジネスロジックでは、以下のメソッドを使用してください：
     * - NOW比較: isAfter(), isBefore()等
     * - 時間差分: diffInSeconds(), diffInDays()等
     * - 日時情報取得: year(), month(), weekOfYear()等
     * 
     * @param string $time 日時文字列
     * @param \DateTimeZone|string|null $tz タイムゾーン
     * @return CarbonImmutable
     */
    public static function parse(string $time, $tz = null): CarbonImmutable
    {
        return CarbonImmutable::parse($time, $tz);
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
