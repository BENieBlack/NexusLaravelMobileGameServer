<?php

namespace NexusGacha\Strategies;

use NexusGacha\DataTransferObjects\StepBonus;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\ValueObjects\GachaPrize;

/**
 * ChoiceDrawStrategy
 *
 * ユーザー選択型のガチャ抽選戦略
 *
 * selection_type='choice'の場合に使用されます。
 * プレイヤーが事前に選択したコンテンツIDに基づいて景品を確定します。
 *
 * 使用例：
 * - "SSRキャラクターA、B、Cの中から好きなキャラクターを1体選択"
 * - "限定装備セット1、2、3の中から好きなセットを選択"
 */
class ChoiceDrawStrategy implements GachaDrawStrategyInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(string $selectionType): bool
    {
        return $selectionType === 'choice';
    }

    /**
     * {@inheritDoc}
     *
     * @throws GachaDrawException
     *                            - CODE_MISSING_CANDIDATE_ID: selectedCandidateIdが指定されていない
     *                            - CODE_INVALID_CANDIDATE: 指定されたIDが無効（存在しない、またはボーナスIDと不一致）
     */
    public function draw(
        StepBonus $bonus,
        ?string $selectedCandidateId,
        string $mstGachaId,
        GachaDrawContext $context
    ): GachaPrize {
        // 1. ユーザー選択IDの検証
        if (! $selectedCandidateId) {
            throw new GachaDrawException(
                'Selected candidate ID is required for choice type',
                GachaDrawException::CODE_MISSING_CANDIDATE_ID
            );
        }

        // 2. 選択されたコンテンツの取得
        $candidate = $context->bonusContentRepository->selectContentById($selectedCandidateId);

        // 3. コンテンツの妥当性検証
        if ($candidate === null || $candidate->getStepBonusId() !== $bonus->getId()) {
            throw new GachaDrawException(
                "Invalid candidate ID: {$selectedCandidateId}",
                GachaDrawException::CODE_INVALID_CANDIDATE
            );
        }

        // 4. 景品DTOを生成
        return new GachaPrize(
            contentType: $candidate->getContentType(),
            contentMstId: $candidate->getContentMstId(),
            contentOption: $candidate->getContentOption(),
            amount: $candidate->getAmount(),
            rarity: $bonus->getBonusRarity() ?? throw new GachaDrawException(
                "Bonus rarity is required for {$bonus->getSelectionType()} type (bonus_id: {$bonus->getId()})",
                GachaDrawException::CODE_MISSING_BONUS_RARITY
            ),
            isGuaranteed: true
        );
    }
}
