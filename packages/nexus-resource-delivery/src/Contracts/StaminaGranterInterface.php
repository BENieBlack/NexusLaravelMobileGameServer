<?php

namespace NexusResourceDelivery\Contracts;

/**
 * StaminaGranterInterface
 *
 * スタミナ付与を抽象化するインターフェース
 * 実装はApplication層で行い、パッケージ層はこれに依存する
 *
 * スタミナは残高ではなく「次の回復時刻」とセットで管理されるため、
 * Walletではなくスタミナ管理側の経路を通す必要がある。
 *
 * 付与先のプレイヤーは引数で受け取る。配送はログインセッションの本人以外
 * （運営からの一斉配布など）にも走りうるため、暗黙のセッション参照にしない。
 */
interface StaminaGranterInterface
{
    /**
     * スタミナを付与する
     *
     * アイテム等による回復と同じ扱いで、最大値を超過してよい。
     *
     * @param  int  $sysPlayerId  付与先プレイヤーID
     * @param  int  $amount  付与量
     * @param  string|null  $staminaType  スタミナ種別（nullなら通常スタミナ）
     */
    public function grantStamina(int $sysPlayerId, int $amount, ?string $staminaType = null): void;
}
