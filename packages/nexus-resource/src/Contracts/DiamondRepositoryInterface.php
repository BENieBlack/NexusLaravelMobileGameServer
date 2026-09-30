<?php

namespace NexusResource\Contracts;

use NexusResource\DataTransferObjects\DiamondBalance;

/**
 * DiamondRepositoryInterface
 *
 * ダイヤモンド残高の永続化操作を抽象化するインターフェース
 * 実装はDomain層で行い、パッケージ層はこのインターフェースに依存する
 */
interface DiamondRepositoryInterface
{
    /**
     * プレイヤーIDとプラットフォームでダイヤモンドを検索
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @return DiamondBalance|null
     */
    public function selectByPlatform(int $sysPlayerId, string $platform): ?DiamondBalance;

    /**
     * プレイヤーIDで全プラットフォームのダイヤモンドを取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return array<DiamondBalance>
     */
    public function selectAllByPlayerId(int $sysPlayerId): array;

    /**
     * ダイヤモンド残高を保存（新規作成 or 更新）
     *
     * @param  DiamondBalance  $diamond
     * @return void
     */
    public function persistDiamond(DiamondBalance $diamond): void;
}
