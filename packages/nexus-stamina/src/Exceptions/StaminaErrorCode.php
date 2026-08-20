<?php

namespace NexusStamina\Exceptions;

/**
 * StaminaErrorCode
 *
 * nexus-staminaパッケージのエラーコード定義（4桁: 1100-1199）
 *
 * パッケージ層エラーコードルール：
 * - 4桁のコードを使用（再利用可能性を考慮）
 * - nexus-staminaの範囲: 1100-1199
 */
class StaminaErrorCode
{
    /**
     * スタミナ不足
     * スタミナを消費しようとした際に残量が不足している
     */
    public const INSUFFICIENT_STAMINA = 1101;
}
