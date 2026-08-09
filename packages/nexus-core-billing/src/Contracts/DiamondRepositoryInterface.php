<?php

namespace NexusBilling\Contracts;

use NexusBilling\DTOs\DiamondBalanceDto;

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
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @return DiamondBalanceDto|null
     */
    public function findByPlatform(int $sysPlayerId, string $platform): ?DiamondBalanceDto;

    /**
     * プレイヤーIDで全プラットフォームのダイヤモンドを取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @return array<DiamondBalanceDto>
     */
    public function findAllByPlayerId(int $sysPlayerId): array;

    /**
     * ダイヤモンド残高を保存（新規作成 or 更新）
     *
     * @param DiamondBalanceDto $diamondDto
     * @return void
     */
    public function saveDiamond(DiamondBalanceDto $diamondDto): void;
}
