<?php

namespace App\Domain\Gacha\Services;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstGacha;
use App\Models\Mst\MstGachaCost;
use App\Repositories\Mst\MstGachaCostRepository;
use App\Repositories\Mst\MstGachaRepository;
use NexusUtilities\ClockUtility;

/**
 * GachaValidationService
 *
 * ガチャのバリデーションを行うサービス
 */
class GachaValidationService
{
    public function __construct(
        private readonly MstGachaRepository $mstGachaRepository,
        private readonly MstGachaCostRepository $mstGachaCostRepository,
    ) {
    }

    /**
     * ガチャマスターデータの存在と有効性を確認
     *
     * @param string $mstGachaId
     * @return MstGacha
     * @throws GameException
     */
    public function validateGachaMaster(string $mstGachaId): MstGacha
    {
        $mstGacha = $this->mstGachaRepository->selectById($mstGachaId);

        if (!$mstGacha) {
            throw new GameException(
                GameErrorCode::GACHA_NOT_FOUND,
                "Gacha not found: {$mstGachaId}"
            );
        }

        if (!$mstGacha->getIsActive()) {
            throw new GameException(
                GameErrorCode::GACHA_INACTIVE,
                "Gacha is inactive: {$mstGachaId}"
            );
        }

        return $mstGacha;
    }

    /**
     * ガチャの開催期間を確認
     *
     * @param MstGacha $mstGacha
     * @return void
     * @throws GameException
     */
    public function validateGachaPeriod(MstGacha $mstGacha): void
    {
        // 開始判定: まだ開始していない（start_at >= NOW）
        $startAt = $mstGacha->getStartAt();
        if ($startAt && ClockUtility::greaterThanOrEqual($startAt)) {
            throw new GameException(
                GameErrorCode::GACHA_NOT_AVAILABLE,
                "Gacha has not started yet"
            );
        }

        // 終了判定: すでに終了している（end_at <= NOW）
        $endAt = $mstGacha->getEndAt();
        if ($endAt && ClockUtility::lessThanOrEqual($endAt)) {
            throw new GameException(
                GameErrorCode::GACHA_NOT_AVAILABLE,
                "Gacha has ended"
            );
        }
    }

    /**
     * 日次実行制限を確認
     *
     * @param MstGacha $mstGacha
     * @param int $currentDailyCount
     * @return void
     * @throws GameException
     */
    public function validateDailyLimit(MstGacha $mstGacha, int $currentDailyCount): void
    {
        $dailyLimit = $mstGacha->getDailyLimit();

        if ($dailyLimit > 0 && $currentDailyCount >= $dailyLimit) {
            throw new GameException(
                GameErrorCode::GACHA_DAILY_LIMIT_EXCEEDED,
                "Daily gacha limit exceeded. Limit: {$dailyLimit}"
            );
        }
    }

    /**
     * ガチャコストの存在を確認
     *
     * @param string $mstGachaId
     * @param int $drawCount
     * @return MstGachaCost
     * @throws GameException
     */
    public function validateGachaCost(string $mstGachaId, int $drawCount): MstGachaCost
    {
        $cost = $this->mstGachaCostRepository->selectByGachaIdAndDrawCount($mstGachaId, $drawCount);

        if (!$cost) {
            throw new GameException(
                GameErrorCode::GACHA_COST_NOT_FOUND,
                "Gacha cost not found for draw_count: {$drawCount}"
            );
        }

        return $cost;
    }
}
