<?php

namespace App\Repositories\Trx;

interface VipLoginBonusHistoryRepositoryInterface
{
    /**
     * VIPログインボーナス履歴を作成
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function insert(array $data, string $connectionName): array;

    /**
     * プレイヤーの最新VIPログインボーナス履歴を取得
     *
     * @return array<string, mixed>|null
     */
    public function selectLatestByPlayerId(int $sysPlayerId, string $connectionName): ?array;

    /**
     * プレイヤーとVIPログインボーナスIDと日付から履歴を取得
     *
     * @return array<string, mixed>|null
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $vipLoginBonusId,
        string $receivedDate,
        string $connectionName
    ): ?array;
}
