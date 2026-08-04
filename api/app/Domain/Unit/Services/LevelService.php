<?php

namespace App\Domain\Unit\Services;

use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Models\Trx\TrxUnit;
use App\Repositories\Mst\MstUnitLevelRepository;
use App\Repositories\Mst\MstUnitRepository;
use App\Repositories\Trx\TrxUnitRepository;
use NexusLevel\Services\_BaseLevelService;

/**
 * LevelService
 * 
 * ユニットレベル管理を担当するサービス
 * _BaseLevelServiceを継承して、ユニット固有のレベルアップ処理を実装
 * 
 * レベルアップ仕様:
 * - 経験値は累積方式（リセットされない）
 * - レアリティごとに最大レベルが異なる
 * - 最大レベル到達後は経験値が増えてもレベルアップしない
 * - ユニットのグレードは変更しない（別途グレードアップ処理が必要）
 */
class LevelService extends _BaseLevelService
{
    /**
     * コンストラクタ
     *
     * @param MstUnitLevelRepository $mstUnitLevelRepository
     * @param MstUnitRepository $mstUnitRepository
     * @param TrxUnitRepository $trxUnitRepository
     */
    public function __construct(
        private readonly MstUnitLevelRepository $mstUnitLevelRepository,
        private readonly MstUnitRepository $mstUnitRepository,
        private readonly TrxUnitRepository $trxUnitRepository,
    ) {
    }

    /**
     * ユニットのレベル情報を取得
     * 
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @return array{level: int, exp: int, exp_to_next: int|null, rarity: string, max_level: int}
     * @throws \Exception ユニットが存在しない場合
     */
    public function getUnitLevel(int $trxUnitId): array
    {
        $trxUnit = $this->trxUnitRepository->selectById($trxUnitId);
        
        if ($trxUnit === null) {
            throw TransactionDataException::unit($trxUnitId);
        }

        // マスターデータからレアリティ情報を取得
        $mstUnit = $this->mstUnitRepository->selectById($trxUnit->getMstUnitId());
        if ($mstUnit === null) {
            throw MasterDataException::unit($trxUnit->getMstUnitId());
        }

        $rarity = $mstUnit->getRarity();
        $maxLevel = $this->mstUnitLevelRepository->getMaxLevel($rarity) ?? 100;
        $expToNext = $this->getExpToNextLevel($rarity, $trxUnit->getLevel(), $trxUnit->getLevelExp());

        return [
            'level' => $trxUnit->getLevel(),
            'exp' => $trxUnit->getLevelExp(),
            'exp_to_next' => $expToNext,
            'rarity' => $rarity,
            'max_level' => $maxLevel,
        ];
    }

    /**
     * ユニットに経験値を加算してレベルアップ処理を行う
     *
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @param int $exp 加算する経験値
     * @return array{
     *   is_leveled_up: bool,
     *   before_level: int,
     *   after_level: int,
     *   total_exp: int,
     *   exp_to_next: int|null,
     *   rarity: string,
     *   max_level: int
     * }
     * @throws \Exception ユニットが存在しない場合
     */
    public function addExpWithDetails(int $trxUnitId, int $exp): array
    {
        // レアリティと最大レベルを事前取得
        $trxUnit = $this->trxUnitRepository->selectById($trxUnitId);
        if ($trxUnit === null) {
            throw TransactionDataException::unit($trxUnitId);
        }
        
        $mstUnit = $this->mstUnitRepository->selectById($trxUnit->getMstUnitId());
        if ($mstUnit === null) {
            throw MasterDataException::unit($trxUnit->getMstUnitId());
        }
        
        $rarity = $mstUnit->getRarity();
        $maxLevel = $this->mstUnitLevelRepository->getMaxLevel($rarity) ?? 100;
        
        // 基底クラスのテンプレートメソッドを呼び出し
        $result = parent::addExp($trxUnitId, $exp);
        
        // ユニット固有の戻り値を追加
        $trxUnit = $this->trxUnitRepository->selectById($trxUnitId);
        $expToNext = $this->getExpToNextLevel($rarity, $trxUnit->getLevel(), $trxUnit->getLevelExp());
        
        return [
            ...$result,
            'exp_to_next' => $expToNext,
            'rarity' => $rarity,
            'max_level' => $maxLevel,
        ];
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     * 
     * @param string|null $rarity レアリティ
     * @param int $currentLevel 現在のレベル
     * @param int $currentExp 現在の累積経験値
     * @return int|null 必要な経験値（最大レベルの場合はnull）
     */
    public function getExpToNextLevel(?string $rarity, int $currentLevel, int $currentExp): ?int
    {
        $nextLevel = $currentLevel + 1;
        $nextLevelData = $this->mstUnitLevelRepository->selectByRarityAndLevel($rarity, $nextLevel);
        
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
    protected function getEntity(mixed $id): object
    {
        $unit = $this->trxUnitRepository->selectById($id);
        
        if ($unit === null) {
            throw TransactionDataException::unit($id);
        }
        
        return $unit;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRarity(object $entity): ?string
    {
        /** @var TrxUnit $entity */
        $mstUnit = $this->mstUnitRepository->selectById($entity->getMstUnitId());
        
        if ($mstUnit === null) {
            throw MasterDataException::unit($entity->getMstUnitId());
        }
        
        return $mstUnit->getRarity();
    }

    /**
     * {@inheritDoc}
     */
    protected function getCurrentLevel(object $entity): int
    {
        /** @var TrxUnit $entity */
        return $entity->getLevel();
    }

    /**
     * {@inheritDoc}
     */
    protected function getCurrentExp(object $entity): int
    {
        /** @var TrxUnit $entity */
        return $entity->getLevelExp();
    }

    /**
     * {@inheritDoc}
     */
    protected function calculateNewLevel(?string $rarity, int $totalExp): int
    {
        return $this->mstUnitLevelRepository->calculateLevelFromExp($rarity, $totalExp);
    }

    /**
     * {@inheritDoc}
     */
    protected function getMaxLevel(?string $rarity): int
    {
        return $this->mstUnitLevelRepository->getMaxLevel($rarity) ?? 100;
    }

    /**
     * {@inheritDoc}
     */
    protected function updateEntity(object $entity, int $level, int $exp): void
    {
        /** @var TrxUnit $entity */
        $entity->setLevel($level);
        $entity->setLevelExp($exp);
        $this->trxUnitRepository->setModel($entity);
    }

    // onLevelUp()はオーバーライドしない（ユニットにはレベルアップ時の追加処理がない）
}
