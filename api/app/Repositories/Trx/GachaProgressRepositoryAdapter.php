<?php

namespace App\Repositories\Trx;

use App\Adapters\Gacha\GachaProgressAdapter;
use NexusGacha\DataTransferObjects\GachaProgress;
use NexusGacha\Repositories\GachaProgressRepositoryInterface;

/**
 * GachaProgressRepositoryAdapter
 *
 * nexus-gachaパッケージのGachaProgressRepositoryInterfaceを実装し、
 * Application層のTrxGachaRepositoryをラップする。
 *
 * Repositoryは常にModelを返し、DTOへの変換はこのアダプタが担う。
 * パッケージ側はApplication層のEloquent Modelに依存できないため、
 * 境界でDTOに詰め替える。
 */
class GachaProgressRepositoryAdapter implements GachaProgressRepositoryInterface
{
    public function __construct(
        private readonly TrxGachaRepository $trxGachaRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?GachaProgress
    {
        $trxGacha = $this->trxGachaRepository->selectByPlayerAndGacha($sysPlayerId, $mstGachaId);

        return $trxGacha ? GachaProgressAdapter::toDto($trxGacha) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(GachaProgress $gachaProgress): void
    {
        $trxGacha = $this->trxGachaRepository->selectByPlayerAndGacha(
            $gachaProgress->getSysPlayerId(),
            $gachaProgress->getMstGachaId()
        );

        if ($trxGacha === null) {
            return;
        }

        $trxGacha->setCurrentStep($gachaProgress->getCurrentStep());
        $trxGacha->setDailyDrawCount($gachaProgress->getDailyDrawCount());
        $trxGacha->setDailyResetAt($gachaProgress->getDailyResetAt());
        $trxGacha->setTotalDrawCount($gachaProgress->getTotalDrawCount());
        $trxGacha->setTotalResetAt($gachaProgress->getTotalResetAt());

        $this->trxGachaRepository->setModel($trxGacha);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(GachaProgress $gachaProgress): GachaProgress
    {
        $this->trxGachaRepository->insertProgress(
            $gachaProgress->getSysPlayerId(),
            $gachaProgress->getMstGachaId(),
            $gachaProgress->getCurrentStep(),
            $gachaProgress->getDailyDrawCount(),
            $gachaProgress->getDailyResetAt(),
            $gachaProgress->getTotalDrawCount(),
            $gachaProgress->getTotalResetAt()
        );

        return $gachaProgress;
    }
}
