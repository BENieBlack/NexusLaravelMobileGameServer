<?php

namespace App\Domain\Equipment\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Equipment\Services\EquipmentLevelService;
use App\Domain\Item\Services\ItemService;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Http\Responses\Equipment\LevelUpResponse;
use App\Repositories\Log\LogEquipmentRepository;
use App\Repositories\Mst\MstItemRepository;
use App\Repositories\Trx\TrxEquipmentRepository;
use App\Traits\RequiresAuthenticationTrait;
use Illuminate\Support\Str;
use NexusResource\Enums\ItemEffectType;

/**
 * LevelUpUseCase
 *
 * 装備経験値アイテムを消費して装備を指定レベルまでレベルアップさせるユースケース
 *
 * 処理フロー:
 * 1. 装備の存在とプレイヤー所有を確認
 * 2. 目標レベルまでに必要な経験値を計算
 * 3. 必要なアイテム数を計算
 * 4. アイテムの所持数を確認
 * 5. アイテムを消費
 * 6. 装備に経験値を加算（自動レベルアップ処理）
 * 7. ログを記録（log_equipment）
 * 8. トランザクション処理をコミット
 */
class LevelUpUseCase extends _BaseUseCase
{
    use RequiresAuthenticationTrait;

    public function __construct(
        private readonly EquipmentLevelService $equipmentLevelService,
        private readonly ItemService $itemService,
        private readonly MstItemRepository $mstItemRepository,
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
        private readonly LogEquipmentRepository $logEquipmentRepository,
    ) {}

    /**
     * バリデーション
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @param  int  $trxEquipmentId  trx_equipment.id（プレイヤー所有装備）
     * @param  string  $mstItemId  mst_item.id（装備経験値アイテム）
     * @param  int  $afterLevel  目標レベル
     *
     * @throws TransactionDataException 装備が存在しない場合
     * @throws GameException 装備がプレイヤーのものでない場合、または目標レベルが現在レベル以下の場合
     * @throws MasterDataException アイテムマスターが存在しない場合
     * @throws BusinessLogicException アイテムタイプが不正、または所持数が不足している場合
     */
    public function validation(int $sysPlayerId, int $trxEquipmentId, string $mstItemId, int $afterLevel): void
    {
        // 1. 装備の存在確認
        $trxEquipment = $this->trxEquipmentRepository->selectById($trxEquipmentId);
        if (! $trxEquipment) {
            throw TransactionDataException::equipment($trxEquipmentId);
        }

        // プレイヤー所有確認
        if ($trxEquipment->getSysPlayerId() !== $sysPlayerId) {
            throw new GameException(
                GameErrorCode::INVALID_PARAMETER,
                'Equipment does not belong to player'
            );
        }

        // 目標レベルが現在レベルより大きいことを確認
        if ($afterLevel <= $trxEquipment->getLevel()) {
            throw new GameException(
                GameErrorCode::INVALID_PARAMETER,
                "Target level must be greater than current level. Current: {$trxEquipment->getLevel()}, Target: {$afterLevel}"
            );
        }

        // 2. 経験値アイテムマスターデータを取得
        $mstItem = $this->mstItemRepository->selectById($mstItemId);
        if (! $mstItem) {
            throw MasterDataException::item($mstItemId);
        }

        // 装備経験値アイテムかチェック
        // 判定は effect（効果種別）で行う。type はアイテムの分類で、効果を決める値ではない
        if (ItemEffectType::tryFromEffect($mstItem->getEffect()) !== ItemEffectType::EQUIPMENT_EXP) {
            throw BusinessLogicException::invalidItemType(
                $mstItemId,
                ItemEffectType::EQUIPMENT_EXP->value,
                $mstItem->getEffect()
            );
        }

        // 3. 目標レベルまでに必要な経験値を計算
        $requiredExp = $this->equipmentLevelService->calculateRequiredExp($trxEquipmentId, $afterLevel);

        // 4. 必要なアイテム数を計算
        $expPerItem = $mstItem->getValue();
        if ($expPerItem <= 0) {
            throw new GameException(
                GameErrorCode::MASTER_DATA_NOT_FOUND,
                "Invalid exp value for item: {$expPerItem}"
            );
        }
        $requiredItemCount = (int) ceil($requiredExp / $expPerItem);

        // 5. アイテム所持数確認
        $currentAmount = $this->itemService->findItemAmount($sysPlayerId, $mstItemId);
        if ($currentAmount < $requiredItemCount) {
            throw BusinessLogicException::itemNotEnough($mstItemId, $requiredItemCount, $currentAmount);
        }
    }

    /**
     * 装備経験値アイテムを使用して指定レベルまでレベルアップ
     *
     * @param  int  $sysPlayerId  sys_player.id（プレイヤーID）
     * @param  int  $trxEquipmentId  trx_equipment.id（プレイヤー所有装備）
     * @param  string  $mstItemId  mst_item.id（装備経験値アイテム）
     * @param  int  $afterLevel  目標レベル
     *
     * @throws \Exception|\Throwable
     */
    public function exec(int $sysPlayerId, int $trxEquipmentId, string $mstItemId, int $afterLevel): LevelUpResponse
    {
        // バリデーション実行
        $this->validation($sysPlayerId, $trxEquipmentId, $mstItemId, $afterLevel);

        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxEquipmentId, $mstItemId, $afterLevel) {
            // ユニークリクエストIDを生成
            $uniqueRequestId = Str::uuid()->toString();

            // 装備データを取得（レベルアップ前の状態を記録するため）
            $trxEquipmentBefore = $this->trxEquipmentRepository->selectById($trxEquipmentId);

            // アイテムマスターデータを再取得（バリデーション済み）
            $mstItem = $this->mstItemRepository->selectById($mstItemId);

            // 必要な経験値を計算
            $requiredExp = $this->equipmentLevelService->calculateRequiredExp($trxEquipmentId, $afterLevel);

            // 必要なアイテム数を計算
            $expPerItem = $mstItem->getValue();
            $requiredItemCount = (int) ceil($requiredExp / $expPerItem);

            // アイテムを消費（消費後のデータを取得）
            $trxItem = $this->itemService->consumeItem($sysPlayerId, $mstItemId, $requiredItemCount);

            // 経験値を加算（更新後の装備データを取得）
            // mst_item.value は double なので、経験値として使う前に整数へ切り捨てる
            // （暗黙のfloat→int変換に任せると、精度が落ちてもPHPのdeprecationにしか出ない）
            $totalExp = (int) ($expPerItem * $requiredItemCount);
            // addExp()は集計結果の配列を返すため、更新後のモデルを返すメソッドを使う
            $trxEquipment = $this->equipmentLevelService->addExpAndReturn($trxEquipmentId, $totalExp);

            // ログを記録
            $this->logEquipmentRepository->insertEquipmentLog(
                uniqueRequestId: $uniqueRequestId,
                sysPlayerId: $sysPlayerId,
                trxEquipmentId: $trxEquipmentId,
                mstEquipmentId: $trxEquipmentBefore->getMstEquipmentId(),
                beforeGrade: $trxEquipmentBefore->getGrade(),
                afterGrade: $trxEquipment->getGrade(),
                beforeLevel: $trxEquipmentBefore->getLevel(),
                beforeLevelExp: $trxEquipmentBefore->getLevelExp(),
                afterLevel: $trxEquipment->getLevel(),
                afterLevelExp: $trxEquipment->getLevelExp(),
            );

            // Responseオブジェクトを生成して返す
            return new LevelUpResponse(
                trxEquipment: $trxEquipment,
                trxItem: $trxItem,
            );
        });
    }
}
