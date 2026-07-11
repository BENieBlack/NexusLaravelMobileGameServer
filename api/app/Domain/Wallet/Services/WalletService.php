<?php

namespace App\Domain\Wallet\Services;

use App\Models\Trx\TrxWallet;
use App\Models\Trx\TrxWalletBalance;
use App\Repositories\Trx\TrxWalletBalanceRepository;
use App\Repositories\Trx\TrxWalletRepository;
use LaravelUtilities\ClockUtility;
use Carbon\CarbonImmutable;

/**
 * WalletService
 * 
 * 汎用通貨の増減処理を担当するサービス
 * Gold, EventCoin, RaidMedal, PvPPoint, GvGPoint等を統合管理
 */
class WalletService
{
    public function __construct(
        private readonly TrxWalletRepository $trxWalletRepository,
        private readonly TrxWalletBalanceRepository $trxWalletBalanceRepository,
    ) {
    }

    /**
     * 通貨を加算
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId 通貨アイテムID（例: "gold", "event_coin"）
     * @param int $freeAmount 無償通貨数（デフォルト: 0）
     * @param int $paidAmount 有償通貨数（デフォルト: 0）
     * @param CarbonImmutable|null $expireAt 有効期限（NULLの場合は無期限）
     * @return array{free_amount: int, paid_amount: int, total_amount: int}
     */
    public function addCurrency(
        int $sysPlayerId,
        string $mstItemId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?CarbonImmutable $expireAt = null
    ): array {
        
        // 1. 現在値を取得または作成（Repository経由）
        $wallet = $this->trxWalletRepository->selectByMstItemId($sysPlayerId, $mstItemId);

        if ($wallet === null) {
            $wallet = new TrxWallet([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'free_amount' => 0,
                'paid_amount' => 0,
            ]);
            $wallet->exists = false; // INSERT として認識
        }

        // 2. 通貨加算（無償/有償を個別管理）
        $wallet->setFreeAmount($wallet->getFreeAmount() + $freeAmount);
        $wallet->setPaidAmount($wallet->getPaidAmount() + $paidAmount);
        $this->trxWalletRepository->setModel($wallet);

        // 3. 無償通貨の残高レコード追加（FIFO用）
        if ($freeAmount > 0) {
            $freeBalance = new TrxWalletBalance([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'current_amount' => $freeAmount,
                'initial_amount' => $freeAmount,
                'expire_at' => $expireAt,
                'is_paid' => false,
            ]);
            $freeBalance->exists = false; // INSERT として認識
            $this->trxWalletBalanceRepository->setModel($freeBalance);
        }

        // 4. 有償通貨の残高レコード追加（FIFO用）
        if ($paidAmount > 0) {
            $paidBalance = new TrxWalletBalance([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'current_amount' => $paidAmount,
                'initial_amount' => $paidAmount,
                'expire_at' => $expireAt,
                'is_paid' => true,
            ]);
            $paidBalance->exists = false; // INSERT として認識
            $this->trxWalletBalanceRepository->setModel($paidBalance);
        }

        return [
            'free_amount' => $freeAmount,
            'paid_amount' => $paidAmount,
            'total_amount' => $wallet->getTotalAmount(),
        ];
    }

    /**
     * 通貨を消費（FIFO方式、有償優先）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId 通貨アイテムID
     * @param int $amount 消費する数量
     * @return array{consumed_amount: int, total_amount: int}
     * @throws \Exception 残高不足の場合
     */
    public function consumeCurrency(
        int $sysPlayerId,
        string $mstItemId,
        int $amount
    ): array {
        // 1. 現在値を取得（Repository経由）
        $wallet = $this->trxWalletRepository->selectByMstItemId($sysPlayerId, $mstItemId);

        if ($wallet === null || $wallet->getTotalAmount() < $amount) {
            throw new \Exception("Insufficient currency: {$mstItemId}");
        }

        // 2. FIFO順で残高を取得（Repository経由）
        // 優先順位: is_paid DESC (有償優先) → expire_at ASC (有効期限が近いものから) → id ASC
        $balanceCollection = $this->trxWalletBalanceRepository->selectAllBalancesByMstItemId($mstItemId);

        // 3. FIFO順で消費（有償優先）
        $remainingAmount = $amount;
        $consumedFree = 0;
        $consumedPaid = 0;

        foreach ($balanceCollection as $balance) {
            if ($remainingAmount <= 0) {
                break;
            }

            $consumeFromBalance = min($balance->getCurrentAmount(), $remainingAmount);
            $balance->setCurrentAmount($balance->getCurrentAmount() - $consumeFromBalance);
            $this->trxWalletBalanceRepository->setModel($balance);

            // 無償/有償の消費数を記録
            if ($balance->getIsPaid()) {
                $consumedPaid += $consumeFromBalance;
            } else {
                $consumedFree += $consumeFromBalance;
            }

            $remainingAmount -= $consumeFromBalance;
        }

        // 4. 現在値を減算（無償/有償を個別に減算）
        $wallet->setFreeAmount($wallet->getFreeAmount() - $consumedFree);
        $wallet->setPaidAmount($wallet->getPaidAmount() - $consumedPaid);
        $this->trxWalletRepository->setModel($wallet);

        return [
            'consumed_amount' => $amount,
            'total_amount' => $wallet->getTotalAmount(),
        ];
    }

    /**
     * 通貨残高を取得
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId 通貨アイテムID
     * @return int 現在の残高（無償+有償の合計）
     */
    public function getBalance(int $sysPlayerId, string $mstItemId): int
    {
        // Repository経由で取得
        $wallet = $this->trxWalletRepository->selectByMstItemId($sysPlayerId, $mstItemId);

        return $wallet?->getTotalAmount() ?? 0;
    }

    /**
     * 有効期限切れの通貨を削除
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId 通貨アイテムID
     * @return int 削除された数量
     */
    public function removeExpiredCurrency(int $sysPlayerId, string $mstItemId): int
    {
        $now = ClockUtility::now();

        // 有効期限切れの残高を取得（Repository経由）
        $expiredBalanceCollection = $this->trxWalletBalanceRepository->selectAllExpiredBalancesByMstItemId($mstItemId, $now);

        $totalExpired = 0;
        $expiredFree = 0;
        $expiredPaid = 0;

        foreach ($expiredBalanceCollection as $balance) {
            $expiredAmount = $balance->getCurrentAmount();
            $totalExpired += $expiredAmount;

            // 無償/有償の期限切れ数を記録
            if ($balance->getIsPaid()) {
                $expiredPaid += $expiredAmount;
            } else {
                $expiredFree += $expiredAmount;
            }

            $balance->setCurrentAmount(0);
            $this->trxWalletBalanceRepository->setModel($balance);
        }

        // 現在値から減算（無償/有償を個別に減算）
        if ($totalExpired > 0) {
            $wallet = $this->trxWalletRepository->selectByMstItemId($sysPlayerId, $mstItemId);

            if ($wallet !== null) {
                $wallet->setFreeAmount(max(0, $wallet->getFreeAmount() - $expiredFree));
                $wallet->setPaidAmount(max(0, $wallet->getPaidAmount() - $expiredPaid));
                $this->trxWalletRepository->setModel($wallet);
            }
        }

        return $totalExpired;
    }
}
