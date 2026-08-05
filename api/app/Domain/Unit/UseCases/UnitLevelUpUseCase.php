<?php

namespace App\Domain\Unit\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Unit\Services\UnitLevelService;
use App\Domain\Item\Services\ItemService;
use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;
use App\Http\Responses\Unit\LevelUpResponse;
use App\Repositories\Mst\MstItemRepository;
use App\Repositories\Trx\TrxUnitRepository;
use App\Traits\RequiresAuthenticationTrait;

/**
 * UnitLevelUpUseCase
 *
 * ユニット経験値アイテムを消費してユニットをレベルアップさせるユースケース
 *
 * 処理フロー:
 * 1. ユニットの存在とプレイヤー所有を確認
 * 2. アイテムの所持数を確認
 * 3. アイテムマスターから経験値量を取得
 * 4. アイテムを消費
 * 5. ユニットに経験値を加算（自動レベルアップ処理）
 * 6. トランザクション処理をコミット
 */
class UnitLevelUpUseCase extends _BaseUseCase
{
    use RequiresAuthenticationTrait;

    public function __construct(
        private readonly UnitLevelService $unitLevelService,
        private readonly ItemService $itemService,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly MstItemRepository $mstItemRepository,
    ) {
    }

    /**
     * バリデーション
     * 
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @param string $mstItemId mst_item.id（マスター定義アイテム）
     * @param int $useCount 使用個数
     * @return void
     * @throws TransactionDataException ユニットが存在しない場合
     * @throws GameException ユニットがプレイヤーのものでない場合
     * @throws MasterDataException アイテムマスターが存在しない場合
     * @throws BusinessLogicException アイテムタイプが不正、または所持数が不足している場合
     */
    public function validation(int $sysPlayerId, int $trxUnitId, string $mstItemId, int $useCount): void
    {
        // 1. ユニットの存在確認
        $trxUnit = $this->trxUnitRepository->selectById($trxUnitId);
        if (!$trxUnit) {
            throw TransactionDataException::unit($trxUnitId);
        }

        // プレイヤー所有確認
        if ($trxUnit->getSysPlayerId() !== $sysPlayerId) {
            throw new GameException(
                GameErrorCode::INVALID_PARAMETER,
                "Unit does not belong to player"
            );
        }

        // 2. アイテムマスターデータを取得
        $mstItem = $this->mstItemRepository->selectById($mstItemId);
        if (!$mstItem) {
            throw MasterDataException::item($mstItemId);
        }

        // アイテムタイプが経験値アイテムかチェック
        if ($mstItem->getType() !== 'UnitEnhancement' || $mstItem->getEffect() !== 'UnitExp') {
            throw BusinessLogicException::invalidItemType(
                $mstItemId,
                'UnitEnhancement/UnitExp',
                "{$mstItem->getType()}/{$mstItem->getEffect()}"
            );
        }

        // 3. アイテム所持数確認
        $currentAmount = $this->itemService->getItemAmount($sysPlayerId, $mstItemId);
        if ($currentAmount < $useCount) {
            throw BusinessLogicException::itemNotEnough($mstItemId, $useCount, $currentAmount);
        }
    }

    /**
     * ユニット経験値アイテムを使用してレベルアップ
     *
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @param string $mstItemId mst_item.id（マスター定義アイテム）
     * @param int $useCount 使用個数
     * @return LevelUpResponse
     * @throws \Exception
     */
    public function exec(int $sysPlayerId, int $trxUnitId, string $mstItemId, int $useCount): LevelUpResponse
    {
        // バリデーション実行
        $this->validation($sysPlayerId, $trxUnitId, $mstItemId, $useCount);

        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxUnitId, $mstItemId, $useCount) {
            // アイテムマスターデータを再取得（バリデーション済み）
            $mstItem = $this->mstItemRepository->selectById($mstItemId);

            // アイテムを消費
            $this->itemService->consumeItem($sysPlayerId, $mstItemId, $useCount);

            // 経験値を計算して加算
            $expPerItem = $mstItem->getValue();
            $totalExp = $expPerItem * $useCount;

            $result = $this->unitLevelService->addExp($trxUnitId, $totalExp);

            // Responseオブジェクトを生成して返す
            return new LevelUpResponse(
                isLeveledUp: $result['is_leveled_up'],
                beforeLevel: $result['before_level'],
                afterLevel: $result['after_level'],
                totalExp: $result['total_exp'],
                expToNext: $result['exp_to_next'],
                rarity: $result['rarity'],
                maxLevel: $result['max_level'],
                itemUsed: $useCount,
                expGained: $totalExp,
            );
        });
    }
}
