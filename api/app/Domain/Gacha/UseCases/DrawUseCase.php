<?php

namespace App\Domain\Gacha\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Gacha\Services\GachaCostService;
use App\Domain\Gacha\Services\GachaProgressService;
use App\Domain\Gacha\Services\GachaValidationService;
use App\Http\Responses\Gacha\DrawResponse;
use App\Models\Trx\TrxGachaHistory;
use App\Repositories\Mst\MstGachaStepRepository;
use App\Repositories\Trx\TrxGachaHistoryRepository;
use App\Traits\RequiresAuthenticationTrait;
use NexusGacha\Services\GachaDrawService;
use NexusGacha\Services\GachaPrizeService;
use NexusGacha\ValueObjects\GachaPrize;

/**
 * DrawUseCase
 *
 * ガチャを実行するユースケース
 */
class DrawUseCase extends _BaseUseCase
{
    use RequiresAuthenticationTrait;

    public function __construct(
        private readonly GachaValidationService $validationService,
        private readonly GachaProgressService $progressService,
        private readonly GachaCostService $costService,
        private readonly GachaDrawService $drawService,
        private readonly GachaPrizeService $prizeService,
        private readonly MstGachaStepRepository $stepRepository,
        private readonly TrxGachaHistoryRepository $historyRepository,
    ) {}

    /**
     * ガチャ実行
     *
     * @throws \Exception
     */
    public function exec(
        int $sysPlayerId,
        string $mstGachaId,
        int $drawCount,
        ?string $selectedCandidateId = null
    ): DrawResponse {
        // 1. バリデーション
        $mstGacha = $this->validationService->validateGachaMaster($mstGachaId);
        $this->validationService->validateGachaPeriod($mstGacha);
        $cost = $this->validationService->validateGachaCost($mstGachaId, $drawCount);

        return $this->executeWithTransaction(function () use (
            $sysPlayerId,
            $mstGachaId,
            $drawCount,
            $selectedCandidateId,
            $mstGacha,
            $cost
        ) {
            // 2. 進行状況取得とリセットチェック
            $progress = $this->progressService->findOrInsertProgress($sysPlayerId, $mstGachaId);
            $progress = $this->progressService->checkAndResetDaily($progress);

            // 3. 日次制限チェック
            $this->validationService->validateDailyLimit($mstGacha, $progress->getDailyDrawCount());

            // 4. コスト消費
            $this->costService->consumeCost($sysPlayerId, $cost);

            // 5. ガチャ抽選
            $currentStep = $progress->getCurrentStep();
            $prizeDtos = $this->drawService->draw(
                $mstGachaId,
                $drawCount,
                $mstGacha->getHasStepUp(),
                $currentStep,
                $selectedCandidateId
            );

            // 6. 景品付与
            $this->prizeService->grantPrizes($sysPlayerId, $prizeDtos);

            // 履歴とレスポンスは配列で扱うため、ここで変換する
            $prizes = array_map(fn (GachaPrize $prize) => $prize->toArray(), $prizeDtos);

            // 7. 次のステップを計算
            $nextStep = $this->calculateNextStep($mstGachaId, $currentStep, $mstGacha->getHasStepUp());

            // 8. 進行状況更新
            $this->progressService->updateProgress($progress, $drawCount, $nextStep);

            // 9. 履歴保存
            $this->persistHistory($sysPlayerId, $mstGachaId, $drawCount, $cost, $prizes);

            // 10. レスポンス生成
            $nextStepInfo = null;
            if ($nextStep && $mstGacha->getHasStepUp()) {
                $nextStepMaster = $this->stepRepository->selectByGachaIdAndStepNumber($mstGachaId, $nextStep);
                if ($nextStepMaster) {
                    $nextStepInfo = [
                        'step_number' => $nextStep,
                        'draw_count' => $nextStepMaster->getAttribute('draw_count'),
                    ];
                }
            }

            return new DrawResponse(
                prizes: $prizes,
                currentStep: $nextStep ?? $currentStep,
                dailyDrawCount: $progress->getDailyDrawCount() + 1,
                totalDrawCount: $progress->getTotalDrawCount() + $drawCount,
                hasNextStep: $nextStepInfo !== null,
                nextStepInfo: $nextStepInfo,
            );
        });
    }

    /**
     * 次のステップを計算
     */
    private function calculateNextStep(string $mstGachaId, int $currentStep, bool $hasStepUp): ?int
    {
        if (! $hasStepUp) {
            return null;
        }

        // 次のステップが存在するかチェック
        $nextStepNumber = $currentStep + 1;
        $nextStep = $this->stepRepository->selectByGachaIdAndStepNumber($mstGachaId, $nextStepNumber);

        if ($nextStep) {
            return $nextStepNumber;
        }

        // 次のステップがない場合、ループするステップを探す
        $steps = $this->stepRepository->selectListByGachaId($mstGachaId);
        $loopStep = $steps->firstWhere('is_loop_start', true);

        if ($loopStep) {
            return $loopStep->getAttribute('step_number');
        }

        // ループもない場合は現在のステップを維持
        return $currentStep;
    }

    /**
     * ガチャ履歴を保存
     *
     * @param  mixed  $cost
     * @param  array<int, array<string, mixed>>  $prizes
     */
    private function persistHistory(int $sysPlayerId, string $mstGachaId, int $drawCount, $cost, array $prizes): void
    {
        $history = new TrxGachaHistory([
            'sys_player_id' => $sysPlayerId,
            'mst_gacha_id' => $mstGachaId,
            'draw_count' => $drawCount,
            'cost_type' => $cost->getAttribute('cost_type'),
            'cost_id' => $cost->getAttribute('cost_id'),
            'cost_amount' => $cost->getAttribute('cost_amount'),
            'prizes' => $prizes,
        ]);
        $history->exists = false;

        $this->historyRepository->setModel($history);
    }
}
