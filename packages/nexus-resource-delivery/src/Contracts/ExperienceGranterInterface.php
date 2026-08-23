<?php

namespace NexusResourceDelivery\Contracts;

/**
 * ExperienceGranterInterface
 *
 * 経験値付与を抽象化するインターフェース
 * 実装はApplication層で行い、パッケージ層はこれに依存する
 *
 * 経験値は累積値を書き換えるだけで先入先出の管理が要らないため、
 * Walletではなくレベル管理側の経路を通す。
 *
 * プレイヤー経験値のほかにユニット経験値・装備経験値の派生が想定されるため、
 * 付与先の種別を targetType で受け取る。どの種別を扱えるかは実装側が決める。
 */
interface ExperienceGranterInterface
{
    /**
     * プレイヤーの経験値
     */
    public const TARGET_PLAYER = 'player';

    /**
     * 経験値を付与する
     *
     * @param  int  $sysPlayerId  付与先プレイヤーID
     * @param  int  $amount  付与量
     * @param  string  $targetType  付与先の種別（player / unit / equipment 等）
     * @param  string|null  $targetId  種別内での対象ID（ユニットや装備を指定する場合）
     *
     * @throws \InvalidArgumentException 実装が対応していない種別を渡した場合
     */
    public function grantExperience(
        int $sysPlayerId,
        int $amount,
        string $targetType = self::TARGET_PLAYER,
        ?string $targetId = null
    ): void;
}
