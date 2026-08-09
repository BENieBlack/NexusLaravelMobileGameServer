<?php

namespace App\Repositories\Trx;

interface VipLoginBonusHistoryRepositoryInterface
{
    /**
     * VIPログインボーナス履歴を作成
     */
    public function create(array $data, string $connectionName): array;

    /**
     * プレイヤーの最新VIPログインボーナス履歴を取得
     */
    public function findLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array;

    /**
     * プレイヤーとVIPログインボーナスIDと日付から履歴を取得
     */
    public function findByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $vipLoginBonusId,
        string $receivedDate,
        string $connectionName
    ): ?array;
}
