<?php

namespace NexusBilling\Services;

use NexusBilling\Contracts\DiamondRepositoryInterface;
use NexusBilling\DataTransferObjects\DiamondBalance;

/**
 * DiamondBalanceService（パッケージ層）
 * 
 * ダイヤモンド残高の管理を担当するサービス
 * 
 * Responsibilities:
 * - ダイヤモンド残高の取得
 * - ダイヤモンドの加算（有償/無償）
 * - ダイヤモンドの消費（無償→有償、または有償のみ）
 * 
 * Characteristics:
 * - DTOベースのビジネスロジック
 * - Repository Interfaceに依存（Model非依存）
 * - 純粋なビジネスルール実装
 * 
 * 消費順序:
 * - デフォルト: 無償ダイヤ → 有償ダイヤ
 * - isPaidOnly=true: 有償ダイヤのみ
 */
class DiamondBalanceService
{
    public function __construct(
        private readonly DiamondRepositoryInterface $diamondRepository,
    ) {
    }

    /**
     * ダイヤモンド残高を取得
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @return array{paid_amount: int, free_amount: int, total_amount: int}
     */
    public function findBalance(int $sysPlayerId, string $platform): array
    {
        $diamond = $this->diamondRepository->selectByPlatform($sysPlayerId, $platform);
        
        if ($diamond === null) {
            return [
                'paid_amount' => 0,
                'free_amount' => 0,
                'total_amount' => 0,
            ];
        }
        
        return [
            'paid_amount' => $diamond->getPaidAmount(),
            'free_amount' => $diamond->getFreeAmount(),
            'total_amount' => $diamond->getTotalAmount(),
        ];
    }

    /**
     * ダイヤモンドを加算（有償/無償）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @param int $amount 加算する数量
     * @param bool $isPaid 有償ダイヤモンドか（falseの場合は無償）
     * @return DiamondBalance 加算後のダイヤモンドDTO
     */
    public function addDiamond(int $sysPlayerId, string $platform, int $amount, bool $isPaid = false): DiamondBalance
    {
        $diamond = $this->diamondRepository->selectByPlatform($sysPlayerId, $platform);

        if ($diamond) {
            // 既存レコードがある場合は加算
            if ($isPaid) {
                $diamond->setPaidAmount($diamond->getPaidAmount() + $amount);
            } else {
                $diamond->setFreeAmount($diamond->getFreeAmount() + $amount);
            }
        } else {
            // 新規レコードを作成
            $diamond = new DiamondBalance(
                sysPlayerId: $sysPlayerId,
                platform: $platform,
                paidAmount: $isPaid ? $amount : 0,
                freeAmount: $isPaid ? 0 : $amount,
            );
        }

        // 保存
        $this->diamondRepository->persistDiamond($diamond);

        return $diamond;
    }

    /**
     * ダイヤモンドを消費（無償 → 有償の順で消費、または有償のみ）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $amount 消費する数量
     * @param bool $isPaidOnly 有償ダイヤのみを消費するか（falseの場合は無償→有償の順）
     * @return void
     * @throws \Exception 残高不足の場合
     */
    public function consumeDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly = false): void
    {
        // 全プラットフォームのダイヤモンドを取得
        $diamondDtos = $this->diamondRepository->selectAllByPlayerId($sysPlayerId);

        if (empty($diamondDtos)) {
            throw new \Exception("ダイヤモンド残高が不足しています。必要: {$amount}, 現在: 0");
        }

        // 合計残高を計算
        $totalFree = array_sum(array_map(fn($dto) => $dto->getFreeAmount(), $diamondDtos));
        $totalPaid = array_sum(array_map(fn($dto) => $dto->getPaidAmount(), $diamondDtos));

        // 残高チェック
        if ($isPaidOnly) {
            $this->validatePaidBalance($totalPaid, $amount);
            $this->consumePaidDiamond($diamondDtos, $amount);
        } else {
            $this->validateTotalBalance($totalFree + $totalPaid, $amount);
            $this->consumeFreeThenPaidDiamond($diamondDtos, $amount);
        }
    }

    // ========================================
    // Private Methods
    // ========================================

    /**
     * 有償ダイヤ残高チェック
     * 
     * @param int $totalPaid 現在の有償ダイヤ残高
     * @param int $required 必要な数量
     * @return void
     * @throws \Exception 残高不足の場合
     */
    private function validatePaidBalance(int $totalPaid, int $required): void
    {
        if ($totalPaid < $required) {
            throw new \Exception("有償ダイヤモンド残高が不足しています。必要: {$required}, 現在: {$totalPaid}");
        }
    }

    /**
     * 合計ダイヤ残高チェック
     * 
     * @param int $total 現在の合計残高
     * @param int $required 必要な数量
     * @return void
     * @throws \Exception 残高不足の場合
     */
    private function validateTotalBalance(int $total, int $required): void
    {
        if ($total < $required) {
            throw new \Exception("ダイヤモンド残高が不足しています。必要: {$required}, 現在: {$total}");
        }
    }

    /**
     * 有償ダイヤのみを消費
     * 
     * @param array<DiamondBalance> $diamondDtos ダイヤモンドDTOの配列
     * @param int $amount 消費する数量
     * @return void
     */
    private function consumePaidDiamond(array $diamondDtos, int $amount): void
    {
        $remaining = $amount;
        
        foreach ($diamondDtos as $diamond) {
            if ($remaining <= 0) break;

            $paidAmount = $diamond->getPaidAmount();
            if ($paidAmount <= 0) continue;

            $consume = min($paidAmount, $remaining);
            $diamond->setPaidAmount($paidAmount - $consume);
            $this->diamondRepository->persistDiamond($diamond);
            $remaining -= $consume;
        }
    }

    /**
     * 無償ダイヤ → 有償ダイヤの順で消費
     * 
     * @param array<DiamondBalance> $diamondDtos ダイヤモンドDTOの配列
     * @param int $amount 消費する数量
     * @return void
     */
    private function consumeFreeThenPaidDiamond(array $diamondDtos, int $amount): void
    {
        $remaining = $amount;

        // まず無償ダイヤから消費
        foreach ($diamondDtos as $diamond) {
            if ($remaining <= 0) break;

            $freeAmount = $diamond->getFreeAmount();
            if ($freeAmount <= 0) continue;

            $consume = min($freeAmount, $remaining);
            $diamond->setFreeAmount($freeAmount - $consume);
            $this->diamondRepository->persistDiamond($diamond);
            $remaining -= $consume;
        }

        // 無償ダイヤで足りない場合は有償ダイヤから消費
        if ($remaining > 0) {
            $this->consumePaidDiamond($diamondDtos, $remaining);
        }
    }
}
