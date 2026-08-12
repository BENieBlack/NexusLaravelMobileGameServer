<?php

namespace NexusGacha\Strategies;

use NexusGacha\ValueObjects\GachaPrize;
use NexusGacha\Exceptions\GachaDrawException;

/**
 * GachaDrawStrategyInterface
 * 
 * ガチャのボーナス景品抽選戦略を定義するインターフェース
 * 
 * このインターフェースを実装することで、新しい抽選タイプ（selection_type）を
 * 既存コードを変更せずに追加できます（Open/Closed Principle）
 * 
 * @see ChoiceDrawStrategy ユーザー選択型の実装例
 * @see RandomDrawStrategy ランダム選択型の実装例
 * @see NoneDrawStrategy 通常抽選型の実装例
 */
interface GachaDrawStrategyInterface
{
    /**
     * このStrategyが対応するselection_typeか判定
     * 
     * @param string $selectionType ボーナスのselection_type属性値
     * @return bool このStrategyで処理できる場合true
     */
    public function supports(string $selectionType): bool;
    
    /**
     * ボーナス景品を抽選
     * 
     * @param mixed $bonus ボーナス情報（MstGachaStepBonusモデル）
     * @param string|null $selectedCandidateId ユーザーが選択したコンテンツID（choice型の場合）
     * @param string $mstGachaId ガチャID
     * @param GachaDrawContext $context 抽選に必要な依存オブジェクト
     * @return GachaPrize 抽選結果の景品情報
     * @throws GachaDrawException 抽選に失敗した場合
     */
    public function draw(
        mixed $bonus,
        ?string $selectedCandidateId,
        string $mstGachaId,
        GachaDrawContext $context
    ): GachaPrize;
}
