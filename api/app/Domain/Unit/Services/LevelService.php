<?php

namespace App\Domain\Unit\Services;

use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Models\Trx\TrxUnit;
use App\Repositories\Mst\MstUnitLevelRepository;
use App\Repositories\Mst\MstUnitRepository;
use App\Repositories\Trx\TrxUnitRepository;
use App\Utilities\ApiSession;

/**
 * LevelService
 * 
 * ユニットレベル管理を担当するサービス
 * - 経験値加算とレベルアップ処理
 * - レアリティに応じたレベル上限の取得
 * - 累積経験値からのレベル計算
 * 
 * レベルアップ仕様:
 * - 経験値は累積方式（リセットされない）
 * - レアリティごとに最大レベルが異なる
 * - 最大レベル到達後は経験値が増えてもレベルアップしない
 * - ユニットのグレードは変更しない（別途グレードアップ処理が必要）
 */
class LevelService
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
    public function addExp(int $trxUnitId, int $exp): array
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
        $beforeLevel = $trxUnit->getLevel();
        
        // 経験値を加算
        $newTotalExp = $trxUnit->getLevelExp() + $exp;
        
        // 新しいレベルを計算
        $afterLevel = $this->mstUnitLevelRepository->calculateLevelFromExp($rarity, $newTotalExp);
        
        // 最大レベルを超えないように制限
        $maxLevel = $this->mstUnitLevelRepository->getMaxLevel($rarity) ?? 100;
        $afterLevel = min($afterLevel, $maxLevel);
        
        $isLeveledUp = ($afterLevel > $beforeLevel);
        
        // ユニット情報を更新（Repository経由）
        $trxUnit->setLevel($afterLevel);
        $trxUnit->setLevelExp($newTotalExp);
        
        // Repository経由で更新（updated_at自動設定、ログ記録も自動的に行われる）
        $this->trxUnitRepository->setModel($trxUnit);
        
        // 次のレベルまでの経験値を計算
        $expToNext = $this->getExpToNextLevel($rarity, $afterLevel, $newTotalExp);
        
        return [
            'is_leveled_up' => $isLeveledUp,
            'before_level' => $beforeLevel,
            'after_level' => $afterLevel,
            'total_exp' => $newTotalExp,
            'exp_to_next' => $expToNext,
            'rarity' => $rarity,
            'max_level' => $maxLevel,
        ];
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     * 
     * @param string $rarity レアリティ
     * @param int $currentLevel 現在のレベル
     * @param int $currentExp 現在の累積経験値
     * @return int|null 必要な経験値（最大レベルの場合はnull）
     */
    public function getExpToNextLevel(string $rarity, int $currentLevel, int $currentExp): ?int
    {
        $nextLevel = $currentLevel + 1;
        $nextLevelData = $this->mstUnitLevelRepository->selectByRarityAndLevel($rarity, $nextLevel);
        
        if ($nextLevelData === null) {
            // 最大レベルに達している
            return null;
        }
        
        return max(0, $nextLevelData->required_exp - $currentExp);
    }

    /**
     * 指定レアリティの最大レベルを取得
     * 
     * @param string $rarity レアリティ
     * @return int 最大レベル
     */
    public function getMaxLevel(string $rarity): int
    {
        return $this->mstUnitLevelRepository->getMaxLevel($rarity) ?? 100;
    }
}
