<?php

namespace App\Domain\Equipment\Services;

use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Models\Trx\TrxEquipment;
use App\Repositories\Mst\MstEquipmentLevelRepository;
use App\Repositories\Mst\MstEquipmentRepository;
use App\Repositories\Trx\TrxEquipmentRepository;
use NexusLevel\Services\_BaseLevelService;

/**
 * EquipmentLevelService
 *
 * 装備レベル管理を担当するサービス
 * _BaseLevelServiceを継承して、装備固有のレベルアップ処理を実装
 *
 * レベルアップ仕様:
 * - 経験値は累積方式（リセットされない）
 * - レアリティごとに最大レベルが異なる
 * - 最大レベル到達後は経験値が増えてもレベルアップしない
 * - 装備のグレードは変更しない（別途グレードアップ処理が必要）
 */
class EquipmentLevelService extends _BaseLevelService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly MstEquipmentLevelRepository $mstEquipmentLevelRepository,
        private readonly MstEquipmentRepository $mstEquipmentRepository,
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
    ) {}

    /**
     * 装備のレベル情報を取得
     *
     * @param  int  $trxEquipmentId  trx_equipment.id（プレイヤー所有装備）
     * @return array{level: int, exp: int, exp_to_next: int|null, rarity: string, max_level: int}
     *
     * @throws \Exception 装備が存在しない場合
     */
    public function findEquipmentLevel(int $trxEquipmentId): array
    {
        $trxEquipment = $this->trxEquipmentRepository->selectById($trxEquipmentId);

        if ($trxEquipment === null) {
            throw TransactionDataException::equipment($trxEquipmentId);
        }

        // マスターデータからレアリティ情報を取得
        $mstEquipment = $this->mstEquipmentRepository->selectById($trxEquipment->getMstEquipmentId());
        if ($mstEquipment === null) {
            throw MasterDataException::equipment($trxEquipment->getMstEquipmentId());
        }

        $rarity = $mstEquipment->getRarity();
        $maxLevel = $this->mstEquipmentLevelRepository->selectMaxLevel($rarity) ?? 100;
        $expToNext = $this->calcExpToNextLevel($rarity, $trxEquipment->getLevel(), $trxEquipment->getLevelExp());

        return [
            'level' => $trxEquipment->getLevel(),
            'exp' => $trxEquipment->getLevelExp(),
            'exp_to_next' => $expToNext,
            'rarity' => $rarity,
            'max_level' => $maxLevel,
        ];
    }

    /**
     * 経験値を加算し、レベルアップ処理を行って装備データを返す
     *
     * @param  int  $trxEquipmentId  trx_equipment.id（プレイヤー所有装備）
     * @param  int  $exp  加算する経験値
     * @return TrxEquipment 更新後の装備データ
     *
     * @throws \Exception 装備が存在しない場合
     */
    public function addExpAndReturn(int $trxEquipmentId, int $exp): TrxEquipment
    {
        // 基底クラスのテンプレートメソッドを呼び出し
        parent::addExp($trxEquipmentId, $exp);

        // 更新後の装備データを返す
        $trxEquipment = $this->trxEquipmentRepository->selectById($trxEquipmentId);
        if ($trxEquipment === null) {
            throw TransactionDataException::equipment($trxEquipmentId);
        }

        return $trxEquipment;
    }

    /**
     * 目標レベルまで上げるのに必要な経験値を計算
     *
     * @param  int  $trxEquipmentId  trx_equipment.id（プレイヤー所有装備）
     * @param  int  $targetLevel  目標レベル
     * @return int 必要な経験値（0の場合は既に目標レベルに到達または超えている）
     *
     * @throws \Exception 装備が存在しない場合
     */
    public function calculateRequiredExp(int $trxEquipmentId, int $targetLevel): int
    {
        $trxEquipment = $this->trxEquipmentRepository->selectById($trxEquipmentId);

        if ($trxEquipment === null) {
            throw TransactionDataException::equipment($trxEquipmentId);
        }

        // マスターデータからレアリティ情報を取得
        $mstEquipment = $this->mstEquipmentRepository->selectById($trxEquipment->getMstEquipmentId());
        if ($mstEquipment === null) {
            throw MasterDataException::equipment($trxEquipment->getMstEquipmentId());
        }

        $rarity = $mstEquipment->getRarity();

        // 目標レベルに必要な累積経験値を取得
        $targetRequiredExp = $this->mstEquipmentLevelRepository->findRequiredExpForLevel($rarity, $targetLevel);

        if ($targetRequiredExp === null) {
            // 目標レベルが存在しない（最大レベルを超えている）
            throw new \Exception("Target level {$targetLevel} does not exist for rarity {$rarity}");
        }

        // 現在の経験値との差分を計算
        $requiredExp = $targetRequiredExp - $trxEquipment->getLevelExp();

        return max(0, $requiredExp);
    }

    /**
     * 累積経験値から理論上のレベルを計算（純粋計算）
     *
     * @param  string  $rarity  レアリティ
     * @param  int  $exp  累積経験値
     * @return int レベル
     */
    public function calculateLevelFromExp(string $rarity, int $exp): int
    {
        return $this->mstEquipmentLevelRepository->calculateLevelFromExp($rarity, $exp);
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     *
     * @param  string|null  $rarity  レアリティ
     * @param  int  $currentLevel  現在のレベル
     * @param  int  $currentExp  現在の累積経験値
     * @return int|null 必要な経験値（最大レベルの場合はnull）
     */
    public function calcExpToNextLevel(?string $rarity, int $currentLevel, int $currentExp): ?int
    {
        $nextLevel = $currentLevel + 1;
        $nextLevelData = $this->mstEquipmentLevelRepository->selectByRarityAndLevel($rarity, $nextLevel);

        if ($nextLevelData === null) {
            // 最大レベルに達している
            return null;
        }

        return max(0, $nextLevelData->required_exp - $currentExp);
    }

    // ========================================
    // Abstract Methods の実装
    // ========================================

    /**
     * {@inheritDoc}
     */
    protected function findEntity(mixed $id): object
    {
        $equipment = $this->trxEquipmentRepository->selectById($id);

        if ($equipment === null) {
            throw TransactionDataException::equipment($id);
        }

        return $equipment;
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveRarity(object $entity): ?string
    {
        /** @var TrxEquipment $entity */
        $mstEquipment = $this->mstEquipmentRepository->selectById($entity->getMstEquipmentId());

        if ($mstEquipment === null) {
            throw MasterDataException::equipment($entity->getMstEquipmentId());
        }

        return $mstEquipment->getRarity();
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveCurrentLevel(object $entity): int
    {
        /** @var TrxEquipment $entity */
        return $entity->getLevel();
    }

    /**
     * {@inheritDoc}
     */
    protected function resolveCurrentExp(object $entity): int
    {
        /** @var TrxEquipment $entity */
        return $entity->getLevelExp();
    }

    /**
     * {@inheritDoc}
     */
    protected function calculateNewLevel(?string $rarity, int $totalExp): int
    {
        return $this->mstEquipmentLevelRepository->calculateLevelFromExp($rarity, $totalExp);
    }

    /**
     * {@inheritDoc}
     */
    protected function findMaxLevel(?string $rarity): int
    {
        return $this->mstEquipmentLevelRepository->selectMaxLevel($rarity) ?? 100;
    }

    /**
     * {@inheritDoc}
     */
    protected function updateEntity(object $entity, int $level, int $exp): void
    {
        /** @var TrxEquipment $entity */
        $entity->setLevel($level);
        $entity->setLevelExp($exp);
        $this->trxEquipmentRepository->setModel($entity);
    }

    // onLevelUp()はオーバーライドしない（装備にはレベルアップ時の追加処理がない）
}
