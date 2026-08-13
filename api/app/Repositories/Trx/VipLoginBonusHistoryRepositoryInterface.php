<?php

namespace App\Repositories\Trx;

interface VipLoginBonusHistoryRepositoryInterface
{
    /**
     * VIPログインボーナス履歴を作成
     */
    public function insert(array $data, string $connectionName): array;

    /**
     * プレイヤーの最新VIPログインボーナス履歴を取得
     */
    public function selectLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array;

    /**
     * プレイヤーとVIPログインボーナスIDと日付から履歴を取得
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $vipLoginBonusId,
        string $receivedDate,
        string $connectionName
    ): ?array;
}
