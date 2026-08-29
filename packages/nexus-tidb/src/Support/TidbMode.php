<?php

namespace NexusTidb\Support;

/**
 * TidbMode
 *
 * TiDBとして扱うかどうかの判定を1か所にまとめる
 */
final class TidbMode
{
    /**
     * テスト用の上書き値（nullなら設定に従う）
     */
    private static ?bool $overrideForTest = null;

    /**
     * TiDBとして扱うか
     */
    public static function isEnabled(): bool
    {
        if (self::$overrideForTest !== null) {
            return self::$overrideForTest;
        }

        return (bool) config('nexus-tidb.is_tidb', false);
    }

    /**
     * テスト用: 判定結果を固定する
     */
    public static function fakeForTest(bool $isEnabled): void
    {
        self::$overrideForTest = $isEnabled;
    }

    /**
     * テスト用: 上書きを解除して設定に戻す
     */
    public static function resetForTest(): void
    {
        self::$overrideForTest = null;
    }
}
