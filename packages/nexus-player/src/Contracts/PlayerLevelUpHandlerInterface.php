<?php

namespace NexusPlayer\Contracts;

/**
 * PlayerLevelUpHandlerInterface
 *
 * レベルアップ時のゲーム固有処理を差し込むためのインターフェース。
 *
 * レベルアップの報酬はゲームごとに違う（スタミナ全回復、アイテム配布など）。
 * それらをこのパッケージが直接持つと nexus-stamina 等への依存が必要になり、
 * nexus-stamina 側は逆にレベル情報を必要とするため相互依存になってしまう。
 * そのため実装はApplication層に置き、ここでは呼び出し口だけを定義する。
 *
 * 実装が登録されていない場合、レベルアップ時には何も起きない。
 */
interface PlayerLevelUpHandlerInterface
{
    /**
     * レベルアップ時に呼ばれる
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $beforeLevel  レベルアップ前のレベル
     * @param  int  $afterLevel  レベルアップ後のレベル
     */
    public function handle(int $sysPlayerId, int $beforeLevel, int $afterLevel): void;
}
