<?php

namespace App\Domain\Item\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Item\Services\ItemService;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\MasterDataException;
use App\Http\Responses\Item\UseResponse;
use App\Repositories\Mst\MstItemRepository;
use NexusResource\Enums\ItemEffectType;
use NexusResourceDelivery\Contracts\ExperienceGranterInterface;
use NexusResourceDelivery\Contracts\StaminaGranterInterface;

/**
 * UseItemUseCase
 *
 * アイテムを消費して mst_item.effect に応じた効果を適用するユースケース
 *
 * 効果の種別は mst_item.effect、1個あたりの効果量は mst_item.value で定義する。
 *
 * 対象の指定が要る効果（ユニット経験値・装備経験値）はここでは扱わない。
 * どのユニット・装備に使うかを受け取る専用のレベルアップAPIがあるため、
 * 入口を二重にせずそちらに寄せる。
 *
 * 処理フロー:
 * 1. アイテムマスターと効果種別を確認
 * 2. 所持数を確認
 * 3. アイテムを消費
 * 4. 効果量 × 使用個数を適用
 */
class UseItemUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly ItemService $itemService,
        private readonly MstItemRepository $mstItemRepository,
        private readonly ExperienceGranterInterface $experienceGranter,
        private readonly StaminaGranterInterface $staminaGranter,
    ) {}

    /**
     * バリデーション
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @param  string  $mstItemId  mst_item.id（マスター定義アイテム）
     * @param  int  $useCount  使用個数
     *
     * @throws MasterDataException アイテムマスターが存在しない場合
     * @throws BusinessLogicException 使用できない効果種別、または所持数が不足している場合
     * @throws GameException 効果量が不正な場合
     */
    public function validation(int $sysPlayerId, string $mstItemId, int $useCount): void
    {
        $mstItem = $this->mstItemRepository->selectById($mstItemId);
        if (! $mstItem) {
            throw MasterDataException::item($mstItemId);
        }

        $effectType = ItemEffectType::tryFromEffect($mstItem->getEffect());

        // 使用に対応していない効果、または対象の指定が要る効果はここでは扱わない
        if ($effectType === null || $effectType->requiresTarget()) {
            throw BusinessLogicException::invalidItemType(
                $mstItemId,
                'PlayerExp/StaminaRecover',
                $mstItem->getEffect()
            );
        }

        if ($mstItem->getValue() <= 0) {
            throw new GameException(
                GameErrorCode::MASTER_DATA_NOT_FOUND,
                "Invalid effect value for item: {$mstItem->getValue()}"
            );
        }

        $currentAmount = $this->itemService->findItemAmount($sysPlayerId, $mstItemId);
        if ($currentAmount < $useCount) {
            throw BusinessLogicException::itemNotEnough($mstItemId, $useCount, $currentAmount);
        }
    }

    /**
     * アイテムを使用して効果を適用する
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @param  string  $mstItemId  mst_item.id（マスター定義アイテム）
     * @param  int  $useCount  使用個数
     *
     * @throws \Exception
     */
    public function exec(int $sysPlayerId, string $mstItemId, int $useCount): UseResponse
    {
        $this->validation($sysPlayerId, $mstItemId, $useCount);

        return $this->executeWithTransaction(function () use ($sysPlayerId, $mstItemId, $useCount) {
            $mstItem = $this->mstItemRepository->selectById($mstItemId);
            $effectType = ItemEffectType::from($mstItem->getEffect());

            $this->itemService->consumeItem($sysPlayerId, $mstItemId, $useCount);

            // mst_item.value は double なので、適用前に整数へ切り捨てる
            // （倍率のような小数値の効果が出てきたら、その効果種別の側で扱いを決める）
            $appliedValue = (int) ($mstItem->getValue() * $useCount);

            match ($effectType) {
                ItemEffectType::PLAYER_EXP => $this->experienceGranter->grantExperience(
                    $sysPlayerId,
                    $appliedValue,
                    ExperienceGranterInterface::TARGET_PLAYER
                ),
                ItemEffectType::STAMINA_RECOVER => $this->staminaGranter->grantStamina(
                    $sysPlayerId,
                    $appliedValue
                ),
                // requiresTarget() の効果は validation で弾いている
                default => throw new GameException(
                    GameErrorCode::INVALID_PARAMETER,
                    "Unsupported item effect: {$effectType->value}"
                ),
            };

            return new UseResponse(
                mstItemId: $mstItemId,
                effect: $effectType->value,
                itemUsed: $useCount,
                appliedValue: $appliedValue,
            );
        });
    }
}
