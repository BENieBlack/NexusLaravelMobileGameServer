<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxGacha;
use App\Persistence\ApiSession;
use NexusGacha\Dto\GachaProgressDto;
use NexusGacha\Repositories\GachaProgressRepositoryInterface;

/**
 * TrxGachaRepository
 *
 * ガチャプレイヤー進行状況Repository
 *
 * @extends _BaseTrxRepository<TrxGacha>
 */
class TrxGachaRepository extends _BaseTrxRepository implements GachaProgressRepositoryInterface
{
    protected string $modelClass = TrxGacha::class;

    /**
     * ユニークキー（sys_player_id, mst_gacha_id の複合キー）
     *
     * @var array<string>
     */
    protected array $uniqueKeys = ['sys_player_id', 'mst_gacha_id'];

    public function __construct(
        private readonly ApiSession $apiSession
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?GachaProgressDto
    {
        $progress = TrxGacha::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_gacha_id', $mstGachaId)
            ->where('is_delete', false)
            ->first();

        if (! $progress) {
            return null;
        }

        return new GachaProgressDto(
            sysPlayerId: $progress->sys_player_id,
            mstGachaId: $progress->mst_gacha_id,
            currentStep: $progress->current_step,
            dailyDrawCount: $progress->daily_draw_count,
            dailyResetAt: $progress->daily_reset_at,
            totalDrawCount: $progress->total_draw_count,
            totalResetAt: $progress->total_reset_at
        );
    }

    /**
     * {@inheritDoc}
     */
    public function save(GachaProgressDto $progressDto): void
    {
        $progress = TrxGacha::query()
            ->where('sys_player_id', $progressDto->getSysPlayerId())
            ->where('mst_gacha_id', $progressDto->getMstGachaId())
            ->where('is_delete', false)
            ->first();

        if ($progress) {
            $progress->current_step = $progressDto->getCurrentStep();
            $progress->daily_draw_count = $progressDto->getDailyDrawCount();
            $progress->daily_reset_at = $progressDto->getDailyResetAt();
            $progress->total_draw_count = $progressDto->getTotalDrawCount();
            $progress->total_reset_at = $progressDto->getTotalResetAt();
            $this->setModel($progress);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(GachaProgressDto $progressDto): GachaProgressDto
    {
        $progress = new TrxGacha([
            'sys_player_id' => $progressDto->getSysPlayerId(),
            'mst_gacha_id' => $progressDto->getMstGachaId(),
            'current_step' => $progressDto->getCurrentStep(),
            'daily_draw_count' => $progressDto->getDailyDrawCount(),
            'daily_reset_at' => $progressDto->getDailyResetAt(),
            'total_draw_count' => $progressDto->getTotalDrawCount(),
            'total_reset_at' => $progressDto->getTotalResetAt(),
        ]);
        $progress->exists = false;
        $this->setModel($progress);

        return $progressDto;
    }
}
