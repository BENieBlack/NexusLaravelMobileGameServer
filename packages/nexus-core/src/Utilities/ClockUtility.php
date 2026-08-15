<?php

namespace Nexus\Core\Utilities;

use Carbon\CarbonImmutable;

class ClockUtility
{
    private static ?CarbonImmutable $currentDatetime = null;
    private static ?string $dayStartTime = null;

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
     * DAY_START_TIMEを取得
     * 
     * @return string HH:MM:SS形式（デフォルト: 00:00:00）
     */
    /**
     * DAY_START_TIMEを取得
     * 
     * @return string HH:MM:SS形式
     */
    public static function calcDayStartTime(): string
    {
        if (self::$dayStartTime === null) {
            self::$dayStartTime = env('DAY_START_TIME', '00:00:00');
        }
        return self::$dayStartTime;
    }

    /**
     * テスト用: DAY_START_TIMEを設定
     * 
     * @param string $time HH:MM:SS形式
     */
    public static function setDayStartTime(string $time): void
    {
        self::$dayStartTime = $time;
    }

    /**
     * 指定日時のゲーム内日付の開始時刻を取得
     * 
     * 例: DAY_START_TIME=09:00:00、$dateTime="2024-01-15 12:00:00"
     *     → "2024-01-15 09:00:00"（その日の開始時刻）
     * 例: DAY_START_TIME=09:00:00、$dateTime="2024-01-15 08:00:00"
     *     → "2024-01-14 09:00:00"（前日の開始時刻）
     * 
     * @param string|null $dateTimeString Y-m-d H:i:s形式の日時文字列（nullの場合は現在時刻）
     * @return CarbonImmutable ゲーム内日付の開始時刻
     */
    public static function calcGameDayStart(?string $dateTimeString = null): CarbonImmutable
    {
        $dateTime = $dateTimeString === null ? self::now() : CarbonImmutable::parse($dateTimeString);
        $dayStartTime = self::calcDayStartTime();
        
        // 指定日時の日付で開始時刻を作成
        $startOfDay = $dateTime->setTimeFromTimeString($dayStartTime);
        
        // 指定日時が開始時刻より前なら、前日の開始時刻を返す
        if ($dateTime->lessThan($startOfDay)) {
            return $startOfDay->subDay();
        }
        
        return $startOfDay;
    }

    /**
     * 2つの日時が同じゲーム内日付かどうかを判定
     * 
     * DAY_START_TIMEを考慮して同一日かを判定します。
     * 例: DAY_START_TIME=09:00:00の場合
     *     - "2024-01-15 10:00:00" と "2024-01-15 20:00:00" → true
     *     - "2024-01-15 08:00:00" と "2024-01-15 10:00:00" → false
     *     - "2024-01-15 10:00:00" と "2024-01-16 08:00:00" → true
     * 
     * @param string $dateTime1 Y-m-d H:i:s形式の日時文字列1
     * @param string $dateTime2 Y-m-d H:i:s形式の日時文字列2
     * @return bool 同じゲーム内日付ならtrue
     */
    public static function isSameGameDay(string $dateTime1, string $dateTime2): bool
    {
        $start1 = self::calcGameDayStart($dateTime1);
        $start2 = self::calcGameDayStart($dateTime2);
        
        return $start1->equalTo($start2);
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
        return (int) self::now()->diffInSeconds($targetTime, false);
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
        return (int) self::now()->diffInMinutes($targetTime, false);
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
        return (int) self::now()->diffInHours($targetTime, false);
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
        return (int) self::now()->diffInDays($targetTime, false);
    }

    /**
     * 指定日時が今日（ゲーム内日付）かどうかをチェック
     * 
     * DAY_START_TIMEを考慮して今日かどうかを判定します。
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool
     */
    public static function isToday(string $dateTimeString): bool
    {
        return self::isSameGameDay(self::nowToString(), $dateTimeString);
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
     * 現在時刻が指定日時以上かどうかをチェック（NOW >= 指定日時）
     * 
     * 用途: 開始済みチェック
     * 例: if (ClockUtility::greaterThanOrEqual($gacha->start_at)) → ガチャ開始済み（期間内チェック）
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool true = NOW >= 指定日時（開始済み）
     */
    public static function greaterThanOrEqual(string $dateTimeString): bool
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return self::now()->greaterThanOrEqualTo($targetTime);
    }

    /**
     * 現在時刻が指定日時以下かどうかをチェック（NOW <= 指定日時）
     * 
     * 用途: 未終了チェック
     * 例: if (ClockUtility::lessThanOrEqual($gacha->end_at)) → ガチャ未終了（期間内チェック）
     * 
     * @param string $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool true = NOW <= 指定日時（未終了）
     */
    public static function lessThanOrEqual(string $dateTimeString): bool
    {
        $targetTime = CarbonImmutable::parse($dateTimeString);
        return self::now()->lessThanOrEqualTo($targetTime);
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
     * 指定日時が現在時刻より過去かどうか
     *
     * Y-m-d H:i:s は固定長のため、辞書順比較が時系列順比較と一致する。
     * Carbonへのパースを挟まずに済むので、日時は文字列のまま扱う。
     *
     * @param string|null $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool nullの場合はfalse
     */
    public static function isPast(?string $dateTimeString): bool
    {
        if ($dateTimeString === null) {
            return false;
        }

        return $dateTimeString < self::nowToString();
    }

    /**
     * 指定日時が現在時刻より未来かどうか
     *
     * @param string|null $dateTimeString Y-m-d H:i:s形式の日時文字列
     * @return bool nullの場合はfalse
     */
    public static function isFuture(?string $dateTimeString): bool
    {
        if ($dateTimeString === null) {
            return false;
        }

        return $dateTimeString > self::nowToString();
    }

    /**
     * 現在時刻が指定期間内かどうか
     *
     * @param string|null $startAt 開始日時（nullなら下限なし）
     * @param string|null $endAt 終了日時（nullなら上限なし）
     */
    public static function isWithin(?string $startAt, ?string $endAt): bool
    {
        $now = self::nowToString();

        if ($startAt !== null && $now < $startAt) {
            return false;
        }

        if ($endAt !== null && $now > $endAt) {
            return false;
        }

        return true;
    }

    /**
     * テスト用：時刻をリセットする
     */
    public static function reset(): void
    {
        self::$currentDatetime = null;
        self::$dayStartTime = null;
        CarbonImmutable::setTestNow(null);
    }

    /**
     * テスト用：時刻を指定した値に設定する
     * 
     * @param string $datetime Y-m-d H:i:s形式の文字列
     */
    public static function setNow(string $datetime): void
    {
        $parsed = CarbonImmutable::parse($datetime);
        self::$currentDatetime = $parsed;
        CarbonImmutable::setTestNow($parsed);
    }
}
