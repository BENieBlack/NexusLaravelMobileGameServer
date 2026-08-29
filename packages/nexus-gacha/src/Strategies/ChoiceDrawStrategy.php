<?php

namespace NexusGacha\Strategies;

use NexusGacha\ValueObjects\GachaPrize;
use NexusGacha\Exceptions\GachaDrawException;

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
     *   - CODE_MISSING_CANDIDATE_ID: selectedCandidateIdが指定されていない
     *   - CODE_INVALID_CANDIDATE: 指定されたIDが無効（存在しない、またはボーナスIDと不一致）
     */
    public function draw(
        mixed $bonus,
        ?string $selectedCandidateId,
        string $mstGachaId,
        GachaDrawContext $context
    ): GachaPrize {
        // 1. ユーザー選択IDの検証
        if (!$selectedCandidateId) {
            throw new GachaDrawException(
                "Selected candidate ID is required for choice type",
                GachaDrawException::CODE_MISSING_CANDIDATE_ID
            );
        }

        // 2. 選択されたコンテンツの取得
        $candidate = $context->bonusContentRepository->selectById($selectedCandidateId);
        
        // 3. コンテンツの妥当性検証
        if (!$candidate || $candidate->getAttribute('mst_gacha_step_bonus_id') !== $bonus->getAttribute('id')) {
            throw new GachaDrawException(
                "Invalid candidate ID: {$selectedCandidateId}",
                GachaDrawException::CODE_INVALID_CANDIDATE
            );
        }

        // 4. 景品DTOを生成
        return new GachaPrize(
            contentType: $candidate->getAttribute('content_type'),
            contentMstId: $candidate->getAttribute('content_mst_id'),
            amount: $candidate->getAttribute('amount'),
            rarity: $bonus->getAttribute('bonus_rarity'),
            isGuaranteed: true
        );
    }
}
