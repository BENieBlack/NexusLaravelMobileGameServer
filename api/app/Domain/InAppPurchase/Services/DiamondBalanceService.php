<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Trx\TrxDiamond;
use App\Models\Trx\TrxDiamondBalance;
use App\Repositories\Trx\TrxDiamondBalanceRepository;
use App\Repositories\Trx\TrxDiamondRepository;

/**
 * DiamondBalanceService
 * 
 * ダイヤモンド残高の管理を担当するサービス
 * 
 * 責任:
 * - ダイヤモンド残高の取得
 * - ダイヤモンドの加算（有償/無償）
 * - ダイヤモンドの消費（無償→有償、または有償のみ）
 * - FIFO用のダイヤモンドバランスレコード作成
 * 
 * 消費順序:
 * - デフォルト: 無償ダイヤ → 有償ダイヤ
 * - isPaidOnly=true: 有償ダイヤのみ
 */
class DiamondBalanceService
{
    public function __construct(
        private readonly TrxDiamondRepository $trxDiamondRepository,
        private readonly TrxDiamondBalanceRepository $trxDiamondBalanceRepository,
    ) {
    }

    /**
     * ダイヤモンド残高を取得
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @return array{paid_amount: int, free_amount: int, total_amount: int}
     */
    public function getBalance(int $sysPlayerId, string $platform): array
    {
        $diamond = $this->trxDiamondRepository->selectByPlatform($sysPlayerId, $platform);
        
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
            'total_amount' => $diamond->getPaidAmount() + $diamond->getFreeAmount(),
        ];
    }

    /**
     * ダイヤモンドを加算（有償/無償）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @param int $amount 加算する数量
     * @param bool $isPaid 有償ダイヤモンドか（falseの場合は無償）
     * @return void
     */
    public function addDiamond(int $sysPlayerId, string $platform, int $amount, bool $isPaid = false): void
    {
        $diamond = $this->trxDiamondRepository->selectByPlatform($sysPlayerId, $platform);

        if ($diamond) {
            // 既存レコードがある場合は加算
            if ($isPaid) {
                $diamond->setPaidAmount($diamond->getPaidAmount() + $amount);
            } else {
                $diamond->setFreeAmount($diamond->getFreeAmount() + $amount);
            }
        } else {
            // 新規レコードを作成
            $diamond = new TrxDiamond([
                'sys_player_id' => $sysPlayerId,
                'platform' => $platform,
                'paid_amount' => $isPaid ? $amount : 0,
                'free_amount' => $isPaid ? 0 : $amount,
            ]);
            $diamond->exists = false; // INSERT として認識
        }

        $this->trxDiamondRepository->setModel($diamond);
    }

    /**
     * 有償ダイヤモンドを加算し、FIFO用のバランスレコードを作成
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @param string $billingPlatform 決済プラットフォーム（AppStore, GooglePlay等）
     * @param int $amount 加算する数量
     * @param float $unitPrice 単価（返金計算用）
     * @return void
     */
    public function addPaidDiamondWithBalance(
        int $sysPlayerId,
        string $platform,
        string $billingPlatform,
        int $amount,
        float $unitPrice
    ): void {
        // ダイヤモンド残高を加算
        $this->addDiamond($sysPlayerId, $platform, $amount, isPaid: true);

        // FIFO用のバランスレコードを作成
        $balance = new TrxDiamondBalance([
            'sys_player_id' => $sysPlayerId,
            'platform' => $platform,
            'billing_platform' => $billingPlatform,
            'current_amount' => $amount,
            'purchase_amount' => $amount,
            'unit_price' => $unitPrice,
        ]);
        $this->trxDiamondBalanceRepository->setModel($balance);
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
        // 全プラットフォームのダイヤモンドを取得（Repository経由）
        $diamonds = $this->trxDiamondRepository->selectByPlayerId($sysPlayerId);

        if ($diamonds->isEmpty()) {
            throw new \Exception("ダイヤモンド残高が不足しています。必要: {$amount}, 現在: 0");
        }

        // 合計残高を計算
        $totalFree = $diamonds->sum(fn($d) => $d->getFreeAmount());
        $totalPaid = $diamonds->sum(fn($d) => $d->getPaidAmount());

        // 残高チェック
        if ($isPaidOnly) {
            $this->validatePaidBalance($totalPaid, $amount);
            $this->consumePaidDiamond($diamonds, $amount);
        } else {
            $this->validateTotalBalance($totalFree + $totalPaid, $amount);
            $this->consumeFreeThenPaidDiamond($diamonds, $amount);
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
     * @param \NexusPersistence\Support\CustomCollection $diamonds ダイヤモンドコレクション
     * @param int $amount 消費する数量
     * @return void
     */
    private function consumePaidDiamond($diamonds, int $amount): void
    {
        $remaining = $amount;
        
        foreach ($diamonds as $diamond) {
            if ($remaining <= 0) break;

            $paidAmount = $diamond->getPaidAmount();
            if ($paidAmount <= 0) continue;

            $consume = min($paidAmount, $remaining);
            $diamond->setPaidAmount($paidAmount - $consume);
            $this->trxDiamondRepository->setModel($diamond);
            $remaining -= $consume;
        }
    }

    /**
     * 無償ダイヤ → 有償ダイヤの順で消費
     * 
     * @param \NexusPersistence\Support\CustomCollection $diamonds ダイヤモンドコレクション
     * @param int $amount 消費する数量
     * @return void
     */
    private function consumeFreeThenPaidDiamond($diamonds, int $amount): void
    {
        $remaining = $amount;

        // まず無償ダイヤから消費
        foreach ($diamonds as $diamond) {
            if ($remaining <= 0) break;

            $freeAmount = $diamond->getFreeAmount();
            if ($freeAmount <= 0) continue;

            $consume = min($freeAmount, $remaining);
            $diamond->setFreeAmount($freeAmount - $consume);
            $this->trxDiamondRepository->setModel($diamond);
            $remaining -= $consume;
        }

        // 無償ダイヤで足りない場合は有償ダイヤから消費
        if ($remaining > 0) {
            $this->consumePaidDiamond($diamonds, $remaining);
        }
    }
}
