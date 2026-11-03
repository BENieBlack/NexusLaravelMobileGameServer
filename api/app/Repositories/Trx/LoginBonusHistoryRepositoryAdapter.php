<?php

namespace App\Repositories\Trx;

use App\Adapters\Login\LoginBonusHistoryAdapter;
use App\Models\Mst\MstLoginBonus;
use App\Repositories\Log\LogLoginBonusReceiveRepository;
use NexusLogin\DataTransferObjects\LoginBonusHistory;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;

/**
 * LoginBonusHistoryRepositoryAdapter
 *
 * TrxLoginBonusRepository / LogLoginBonusReceiveRepository（Model）と
 * LoginBonusHistoryRepositoryInterface（DTO）の橋渡し。
 * 最新状態は trx、受取履歴は log へ書く
 */
class LoginBonusHistoryRepositoryAdapter implements LoginBonusHistoryRepositoryInterface
{
    public function __construct(
        private readonly TrxLoginBonusRepository $trxLoginBonusRepository,
        private readonly LogLoginBonusReceiveRepository $logLoginBonusReceiveRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectLatestByPlayer(int $sysPlayerId): ?LoginBonusHistory
    {
        return $this->selectByType($sysPlayerId, MstLoginBonus::TYPE_DAILY);
    }

    /**
     * {@inheritDoc}
     */
    public function selectLatestByPlayerId(int $sysPlayerId): ?LoginBonusHistory
    {
        return $this->selectLatestByPlayer($sysPlayerId);
    }

    /**
     * {@inheritDoc}
     */
    public function countUniqueDaysSince(int $sysPlayerId, string $sinceDate): int
    {
        $loginBonusHistory = $this->selectLatestByPlayer($sysPlayerId);

        return $loginBonusHistory !== null && $loginBonusHistory->getReceivedDate() >= $sinceDate ? 1 : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function insertReceive(
        int $sysPlayerId,
        string $type,
        string $mstLoginBonusId,
        int $day,
        ?int $absentDays,
        string $receivedDate,
        string $rewardType,
        string $rewardMstId,
        int $rewardAmount,
        bool $isPaid,
    ): void {
        $this->trxLoginBonusRepository->upsertLatest(
            sysPlayerId: $sysPlayerId,
            type: $type,
            mstLoginBonusId: $mstLoginBonusId,
            day: $day,
            absentDays: $absentDays,
            receivedDate: $receivedDate,
        );

        $this->logLoginBonusReceiveRepository->insertReceive(
            sysPlayerId: $sysPlayerId,
            type: $type,
            mstLoginBonusId: $mstLoginBonusId,
            day: $day,
            absentDays: $absentDays,
            receivedAt: $receivedDate,
            rewardType: $rewardType,
            rewardMstId: $rewardMstId,
            rewardAmount: $rewardAmount,
            isPaid: $isPaid,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function selectFirstComebackByPlayerId(int $sysPlayerId): ?LoginBonusHistory
    {
        return $this->selectByType($sysPlayerId, MstLoginBonus::TYPE_COMEBACK);
    }

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerAndBonusAndDate(
        int $sysPlayerId,
        string $bonusId,
        string $receivedDate,
    ): ?LoginBonusHistory {
        $loginBonusHistory = $this->selectByType($sysPlayerId, MstLoginBonus::TYPE_COMEBACK);

        if ($loginBonusHistory === null
            || $loginBonusHistory->getMstLoginBonusId() !== $bonusId
            || $loginBonusHistory->getReceivedDate() !== $receivedDate) {
            return null;
        }

        return $loginBonusHistory;
    }

    private function selectByType(int $sysPlayerId, string $type): ?LoginBonusHistory
    {
        $trxLoginBonus = $this->trxLoginBonusRepository->selectByType($sysPlayerId, $type);

        return $trxLoginBonus === null ? null : LoginBonusHistoryAdapter::toDto($trxLoginBonus);
    }
}
