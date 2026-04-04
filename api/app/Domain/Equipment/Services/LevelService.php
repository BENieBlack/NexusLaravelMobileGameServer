<?php

namespace App\Domain\Equipment\Services;

use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Models\Trx\TrxEquipment;
use App\Repositories\Mst\MstEquipmentLevelRepository;
use App\Repositories\Mst\MstEquipmentRepository;
use App\Repositories\Trx\TrxEquipmentRepository;
use App\Persistence\ApiSession;

/**
 * LevelService
 * 
 * 装備レベル管理を担当するサービス
 * - 経験値加算とレベルアップ処理
 * - レアリティに応じたレベル上限の取得
 * - 累積経験値からのレベル計算
 * 
 * レベルアップ仕様:
 * - 経験値は累積方式（リセットされない）
 * - レアリティごとに最大レベルが異なる
 * - 最大レベル到達後は経験値が増えてもレベルアップしない
 * - 装備のグレードは変更しない（別途グレードアップ処理が必要）
 */
class LevelService
{
    /**
     * コンストラクタ
     *
     * @param MstEquipmentLevelRepository $mstEquipmentLevelRepository
     * @param MstEquipmentRepository $mstEquipmentRepository
     * @param TrxEquipmentRepository $trxEquipmentRepository
     */
    public function __construct(
        private readonly MstEquipmentLevelRepository $mstEquipmentLevelRepository,
        private readonly MstEquipmentRepository $mstEquipmentRepository,
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
    ) {
    }

    /**
     * 装備のレベル情報を取得
     * プレイヤーIDはApiSessionから自動的に取得される
     * 
     * @param int $trxEquipmentId trx_equipment.id（プレイヤー所有装備）
     * @return array{level: int, exp: int, exp_to_next: int|null, rarity: string, max_level: int}
     * @throws \Exception 装備が存在しない場合
     */
    public function getEquipmentLevel(int $trxEquipmentId): array
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
        $maxLevel = $this->mstEquipmentLevelRepository->getMaxLevel($rarity) ?? 100;
        $expToNext = $this->getExpToNextLevel($rarity, $trxEquipment->getLevel(), $trxEquipment->getLevelExp());

        return [
            'level' => $trxEquipment->getLevel(),
            'exp' => $trxEquipment->getLevelExp(),
            'exp_to_next' => $expToNext,
            'rarity' => $rarity,
            'max_level' => $maxLevel,
        ];
    }

    /**
     * 経験値を加算し、レベルアップ処理を行う
     * プレイヤーIDはApiSessionから自動的に取得される
     * 
     * レベルアップした場合:
     * - trx_equipment.levelとlevel_expを更新
     * 
     * @param int $trxEquipmentId trx_equipment.id（プレイヤー所有装備）
     * @param int $exp 加算する経験値
     * @return TrxEquipment 更新後の装備データ
     * @throws \Exception 装備が存在しない場合
     */
    public function addExp(int $trxEquipmentId, int $exp): TrxEquipment
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
        $beforeLevel = $trxEquipment->getLevel();
        
        // 経験値を加算
        $newTotalExp = $trxEquipment->getLevelExp() + $exp;
        
        // 新しいレベルを計算
        $afterLevel = $this->mstEquipmentLevelRepository->calculateLevelFromExp($rarity, $newTotalExp);
        
        // 最大レベルを超えないように制限
        $maxLevel = $this->mstEquipmentLevelRepository->getMaxLevel($rarity) ?? 100;
        $afterLevel = min($afterLevel, $maxLevel);
        
        $isLeveledUp = ($afterLevel > $beforeLevel);
        
        // 装備情報を更新（Repository経由）
        $trxEquipment->setLevel($afterLevel);
        $trxEquipment->setLevelExp($newTotalExp);
        
        // Repository経由で更新（updated_at自動設定）
        $this->trxEquipmentRepository->setModel($trxEquipment);
        
        // 更新後の装備データを返す
        return $trxEquipment;
    }

    /**
     * 目標レベルまで上げるのに必要な経験値を計算
     * プレイヤーIDはApiSessionから自動的に取得される
     * 
     * @param int $trxEquipmentId trx_equipment.id（プレイヤー所有装備）
     * @param int $targetLevel 目標レベル
     * @return int 必要な経験値（0の場合は既に目標レベルに到達または超えている）
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
        $targetRequiredExp = $this->mstEquipmentLevelRepository->getRequiredExpForLevel($rarity, $targetLevel);
        
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
     * @param string $rarity レアリティ
     * @param int $exp 累積経験値
     * @return int レベル
     */
    public function calculateLevelFromExp(string $rarity, int $exp): int
    {
        return $this->mstEquipmentLevelRepository->calculateLevelFromExp($rarity, $exp);
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
        $nextLevelData = $this->mstEquipmentLevelRepository->selectByRarityAndLevel($rarity, $nextLevel);
        
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
        return $this->mstEquipmentLevelRepository->getMaxLevel($rarity) ?? 100;
    }
}
