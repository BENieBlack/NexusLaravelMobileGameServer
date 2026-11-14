<?php

namespace NexusStamina\Services;

/**
 * StaminaRecoveryIntervalProviderInterface
 *
 * スタミナの回復間隔（秒）の読み出し口。
 *
 * 値の出どころ（マスターの設定表など）はパッケージの外に置き、
 * 実装はアプリ側のアダプタが担う（パッケージはアプリに依存しない）。
 */
interface StaminaRecoveryIntervalProviderInterface
{
    /**
     * スタミナが 1 回復する間隔（秒）を取得
     *
     * @return int 常に 1 以上の整数（呼び出し側は 0 除算を気にしなくてよい）
     */
    public function findRecoveryIntervalSeconds(): int;
}
