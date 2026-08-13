<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxGacha;
use NexusGacha\Dto\GachaProgressDto;
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
    public function selectByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?GachaProgressDto
    {
        $model = $this->trxGachaRepository->selectByPlayerAndGacha($sysPlayerId, $mstGachaId);

        return $model ? $this->convertToDto($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function persist(GachaProgressDto $gachaProgressDto): void
    {
        $model = $this->trxGachaRepository->selectByPlayerAndGacha(
            $gachaProgressDto->getSysPlayerId(),
            $gachaProgressDto->getMstGachaId()
        );

        if ($model === null) {
            return;
        }

        $model->current_step = $gachaProgressDto->getCurrentStep();
        $model->daily_draw_count = $gachaProgressDto->getDailyDrawCount();
        $model->daily_reset_at = $gachaProgressDto->getDailyResetAt();
        $model->total_draw_count = $gachaProgressDto->getTotalDrawCount();
        $model->total_reset_at = $gachaProgressDto->getTotalResetAt();

        $this->trxGachaRepository->setModel($model);
    }

    /**
     * {@inheritDoc}
     */
    public function insert(GachaProgressDto $gachaProgressDto): GachaProgressDto
    {
        $this->trxGachaRepository->insertProgress(
            $gachaProgressDto->getSysPlayerId(),
            $gachaProgressDto->getMstGachaId(),
            $gachaProgressDto->getCurrentStep(),
            $gachaProgressDto->getDailyDrawCount(),
            $gachaProgressDto->getDailyResetAt(),
            $gachaProgressDto->getTotalDrawCount(),
            $gachaProgressDto->getTotalResetAt()
        );

        return $gachaProgressDto;
    }

    /**
     * Eloquent ModelをDTOに変換
     */
    private function convertToDto(TrxGacha $model): GachaProgressDto
    {
        return new GachaProgressDto(
            sysPlayerId: $model->sys_player_id,
            mstGachaId: $model->mst_gacha_id,
            currentStep: $model->current_step,
            dailyDrawCount: $model->daily_draw_count,
            dailyResetAt: $model->daily_reset_at,
            totalDrawCount: $model->total_draw_count,
            totalResetAt: $model->total_reset_at
        );
    }
}
