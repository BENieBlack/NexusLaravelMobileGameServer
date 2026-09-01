<?php

namespace NexusGacha\Strategies;

use Illuminate\Database\Eloquent\Model;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\ValueObjects\GachaPrize;

/**
 * RandomDrawStrategy
 *
 * ランダム選択型のガチャ抽選戦略
 *
 * selection_type='random'の場合に使用されます。
 * 登録された候補コンテンツの中から、重み（weight）に基づいてランダムに1つを抽選します。
 *
 * 使用例：
 * - "SSRキャラクター5体の中からランダムに1体確定"
 * - "限定アイテムセット3種の中からランダムに1セット"
 */
class RandomDrawStrategy implements GachaDrawStrategyInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(string $selectionType): bool
    {
        return $selectionType === 'random';
    }

    /**
     * {@inheritDoc}
     *
     * @throws GachaDrawException
     *                            - CODE_NO_CANDIDATES: 候補コンテンツが見つからない
     *                            - CODE_EMPTY_ITEMS: 重み付き抽選で候補が空
     */
    public function draw(
        mixed $bonus,
        ?string $selectedCandidateId,
        string $mstGachaId,
        GachaDrawContext $context
    ): GachaPrize {
        // 1. 候補コンテンツを取得
        $candidates = $context->bonusContentRepository->selectByBonusId($bonus->getAttribute('id'));

        // 2. 候補が存在するか検証
        if ($candidates->isEmpty()) {
            throw new GachaDrawException(
                "No candidates found for random selection (bonus_id: {$bonus->getAttribute('id')})",
                GachaDrawException::CODE_NO_CANDIDATES
            );
        }

        // 3. 重み付きランダム抽選
        $candidate = $this->weightedRandom($candidates->all(), 'weight');

        // 4. 景品DTOを生成
        return new GachaPrize(
            contentType: $candidate->getAttribute('content_type'),
            contentMstId: $candidate->getAttribute('content_mst_id'),
            amount: $candidate->getAttribute('amount'),
            rarity: $bonus->getAttribute('bonus_rarity'),
            isGuaranteed: true
        );
    }

    /**
     * 重み付きランダム抽選
     *
     * @param  array<array-key, Model>  $items  候補アイテム配列
     * @param  string  $weightKey  重みを取得するための属性キー
     * @return mixed 抽選されたアイテム
     *
     * @throws GachaDrawException 候補が空の場合
     */
    private function weightedRandom(array $items, string $weightKey): mixed
    {
        if (empty($items)) {
            throw new GachaDrawException(
                'No items available for weighted random selection',
                GachaDrawException::CODE_EMPTY_ITEMS
            );
        }

        // 総重みを計算
        $totalWeight = array_sum(array_map(fn ($item) => $item->getAttribute($weightKey), $items));

        // ランダム値を生成（1 ～ totalWeight）
        $rand = rand(1, $totalWeight);

        // 累積重みで抽選
        $accumulated = 0;
        foreach ($items as $item) {
            $accumulated += $item->getAttribute($weightKey);
            if ($rand <= $accumulated) {
                return $item;
            }
        }

        // フォールバック（通常は到達しない）
        return $items[0];
    }
}
