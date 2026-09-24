<?php

namespace NexusResourceDelivery\Contracts;

/**
 * EquipmentRepositoryInterface
 *
 * 装備付与の永続化を抽象化するインターフェース
 * 実装はApplication層（Eloquent）で行い、パッケージ層はこれに依存する
 *
 * 付与先のプレイヤーは引数で受け取る。配送はログインセッションの本人以外
 * （運営からの一斉配布など）にも走りうるため、暗黙のセッション参照にしない。
 */
interface EquipmentRepositoryInterface
{
    /**
     * 装備を1つ付与する
     *
     * @param  int  $sysPlayerId  付与先プレイヤーID
     * @param  string  $mstEquipmentId  装備のマスターID
     * @param  int|null  $level  初期レベル（nullなら既定値）
     * @param  int|null  $grade  初期グレード（nullなら既定値）
     */
    public function insertEquipment(int $sysPlayerId, string $mstEquipmentId, ?int $level = null, ?int $grade = null): void;
}
