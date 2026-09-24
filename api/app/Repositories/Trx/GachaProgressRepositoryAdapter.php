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
        $model = $this->trxGachaRepository->selectByPlayerAndGacha($sysPlayerId, $mstGachaId);

        return $model ? GachaProgressAdapter::toDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(GachaProgress $gachaProgress): void
    {
        $model = $this->trxGachaRepository->selectByPlayerAndGacha(
            $gachaProgress->getSysPlayerId(),
            $gachaProgress->getMstGachaId()
        );

        if ($model === null) {
            return;
        }

        $model->current_step = $gachaProgress->getCurrentStep();
        $model->daily_draw_count = $gachaProgress->getDailyDrawCount();
        $model->daily_reset_at = $gachaProgress->getDailyResetAt();
        $model->total_draw_count = $gachaProgress->getTotalDrawCount();
        $model->total_reset_at = $gachaProgress->getTotalResetAt();

        $this->trxGachaRepository->setModel($model);
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
