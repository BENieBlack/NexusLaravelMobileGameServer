<?php

namespace App\Repositories\Trx;

interface VipLoginBonusHistoryRepositoryInterface
{
    /**
     * VIPログインボーナス履歴を作成
     *
     * @param array $data
     * @param string $connectionName
     * @return array
     */
    public function create(array $data, string $connectionName): array;

    /**
     * プレイヤーの最新VIPログインボーナス履歴を取得
     *
     * @param int $sysPlayerId
     * @param string $connectionName
     * @return array|null
     */
    public function findLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array;

    /**
     * プレイヤーとVIPログインボーナスIDと日付から履歴を取得
     *
     * @param int $sysPlayerId
     * @param string $vipLoginBonusId
     * @param string $receivedDate
     * @param string $connectionName
     * @return array|null
     */
    public function findByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $vipLoginBonusId,
        string $receivedDate,
        string $connectionName
    ): ?array;
}
