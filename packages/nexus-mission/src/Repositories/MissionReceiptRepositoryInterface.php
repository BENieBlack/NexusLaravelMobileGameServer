<?php

namespace NexusMission\Repositories;

use NexusMission\DataTransferObjects\MissionReceipt;

/**
 * MissionReceiptRepositoryInterface
 *
 * ミッションの受け取り記録（trx_mission）へのアクセスを抽象化する
 */
interface MissionReceiptRepositoryInterface
{
    /**
     * プレイヤーの受け取り記録を全件取得する
     *
     * @return array<int, MissionReceipt>
     */
    public function selectByPlayerId(int $sysPlayerId): array;

    /**
     * ミッション1件の受け取り記録を取得する（未受け取りならnull）
     */
    public function selectByMissionId(int $sysPlayerId, string $mstMissionId): ?MissionReceipt;

    /**
     * 受け取り記録を1件追加する
     *
     * 受け取り済みのミッションが渡ることは無い前提（MissionServiceが事前に判定する）
     */
    public function insert(MissionReceipt $missionReceipt): void;
}
