<?php

namespace NexusResourceDelivery\Contracts;

/**
 * UnitRepositoryInterface
 *
 * ユニット付与の永続化を抽象化するインターフェース
 * 実装はApplication層（Eloquent）で行い、パッケージ層はこれに依存する
 *
 * 付与先のプレイヤーは引数で受け取る。配送はログインセッションの本人以外
 * （運営からの一斉配布など）にも走りうるため、暗黙のセッション参照にしない。
 */
interface UnitRepositoryInterface
{
    /**
     * ユニットを1体付与する
     *
     * @param  int  $sysPlayerId  付与先プレイヤーID
     * @param  string  $mstUnitId  ユニットのマスターID
     * @param  int|null  $grade  初期グレード（nullなら既定値）
     * @param  int|null  $level  初期レベル（nullなら既定値）
     */
    public function insertUnit(int $sysPlayerId, string $mstUnitId, ?int $grade = null, ?int $level = null): void;
}
