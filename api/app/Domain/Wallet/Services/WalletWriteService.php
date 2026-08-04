<?php

namespace App\Domain\Wallet\Services;

use App\Models\Trx\TrxWallet;
use App\Models\Trx\TrxWalletBalance;
use App\Repositories\Trx\TrxWalletBalanceRepository;
use App\Repositories\Trx\TrxWalletRepository;
use LaravelWallet\DTOs\CurrencyOperationResultDto;
use LaravelWallet\Exceptions\InsufficientBalanceException;
use NexusUtilities\ClockUtility;

/**
 * WalletWriteService
 * 
 * 通貨残高の書き込み操作を担当するサービス
 * 
 * 責任:
 * - 通貨の加算（FIFOバランスレコード作成を含む）
 * - 通貨の消費（FIFO方式、有償優先）
 * - 有効期限切れ通貨の削除
 * - 状態変更あり
 * 
 * 設計:
 * - Read側: WalletReadService（状態変更なし）
 * - Write側: このサービス（状態変更あり）
 */
class WalletWriteService
{
    public function __construct(
        private readonly TrxWalletRepository $trxWalletRepository,
        private readonly TrxWalletBalanceRepository $trxWalletBalanceRepository,
    ) {
    }

    /**
     * 通貨を加算
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID（例: "gold", "event_coin"）
     * @param int $freeAmount 無償通貨数（デフォルト: 0）
     * @param int $paidAmount 有償通貨数（デフォルト: 0）
     * @param string|null $expireAt 有効期限 (Y-m-d H:i:s)（NULLの場合は無期限）
     * @return CurrencyOperationResultDto 操作結果
     */
    public function addCurrency(
        int $playerId,
        string $currencyId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?string $expireAt = null
    ): CurrencyOperationResultDto {
        
        // 1. 現在値を取得または作成（Repository経由）
        $wallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

        if ($wallet === null) {
            $wallet = new TrxWallet([
                'sys_player_id' => $playerId,
                'mst_item_id' => $currencyId,
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
            $this->createBalanceRecord($playerId, $currencyId, $freeAmount, $expireAt, isPaid: false);
        }

        // 4. 有償通貨の残高レコード追加（FIFO用）
        if ($paidAmount > 0) {
            $this->createBalanceRecord($playerId, $currencyId, $paidAmount, $expireAt, isPaid: true);
        }

        return new CurrencyOperationResultDto(
            freeAmount: $freeAmount,
            paidAmount: $paidAmount,
            totalAmount: $freeAmount + $paidAmount,
            currentBalance: $wallet->getTotalAmount(),
        );
    }

    /**
     * 通貨を消費（FIFO方式、有償優先）
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param int $amount 消費する数量
     * @return CurrencyOperationResultDto 操作結果
     * @throws InsufficientBalanceException 残高不足の場合
     */
    public function consumeCurrency(
        int $playerId,
        string $currencyId,
        int $amount
    ): CurrencyOperationResultDto {
        // 1. 現在値を取得（Repository経由）
        $wallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

        if ($wallet === null || $wallet->getTotalAmount() < $amount) {
            $available = $wallet?->getTotalAmount() ?? 0;
            throw new InsufficientBalanceException($currencyId, $amount, $available);
        }

        // 2. FIFO順で残高を取得（Repository経由）
        // 優先順位: is_paid DESC (有償優先) → expire_at ASC (有効期限が近いものから) → id ASC
        $balanceCollection = $this->trxWalletBalanceRepository->selectAllBalancesByMstItemId($currencyId);

        // 3. FIFO順で消費（有償優先）
        $consumedAmounts = $this->consumeFromBalances($balanceCollection, $amount);

        // 4. 現在値を減算（無償/有償を個別に減算）
        $wallet->setFreeAmount($wallet->getFreeAmount() - $consumedAmounts['free']);
        $wallet->setPaidAmount($wallet->getPaidAmount() - $consumedAmounts['paid']);
        $this->trxWalletRepository->setModel($wallet);

        return new CurrencyOperationResultDto(
            freeAmount: $consumedAmounts['free'],
            paidAmount: $consumedAmounts['paid'],
            totalAmount: $amount,
            currentBalance: $wallet->getTotalAmount(),
        );
    }

    /**
     * 有効期限切れの通貨を削除
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return int 削除された数量
     */
    public function removeExpiredCurrency(int $playerId, string $currencyId): int
    {
        $now = ClockUtility::now();

        // 有効期限切れの残高を取得（Repository経由）
        $expiredBalanceCollection = $this->trxWalletBalanceRepository->selectAllExpiredBalancesByMstItemId($currencyId, $now);

        $expiredAmounts = $this->removeExpiredBalances($expiredBalanceCollection);

        // 現在値から減算（無償/有償を個別に減算）
        if ($expiredAmounts['total'] > 0) {
            $wallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

            if ($wallet !== null) {
                $wallet->setFreeAmount(max(0, $wallet->getFreeAmount() - $expiredAmounts['free']));
                $wallet->setPaidAmount(max(0, $wallet->getPaidAmount() - $expiredAmounts['paid']));
                $this->trxWalletRepository->setModel($wallet);
            }
        }

        return $expiredAmounts['total'];
    }

    // ========================================
    // Private Methods
    // ========================================

    /**
     * バランスレコードを作成
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param int $amount 数量
     * @param string|null $expireAt 有効期限
     * @param bool $isPaid 有償通貨か
     * @return void
     */
    private function createBalanceRecord(
        int $playerId,
        string $currencyId,
        int $amount,
        ?string $expireAt,
        bool $isPaid
    ): void {
        $balance = new TrxWalletBalance([
            'sys_player_id' => $playerId,
            'mst_item_id' => $currencyId,
            'current_amount' => $amount,
            'initial_amount' => $amount,
            'expire_at' => $expireAt,
            'is_paid' => $isPaid,
        ]);
        $balance->exists = false; // INSERT として認識
        $this->trxWalletBalanceRepository->setModel($balance);
    }

    /**
     * バランスから通貨を消費（FIFO）
     * 
     * @param \NexusPersistence\Support\CustomCollection $balanceCollection バランスコレクション
     * @param int $amount 消費する数量
     * @return array{free: int, paid: int} 消費した無償/有償の数量
     */
    private function consumeFromBalances($balanceCollection, int $amount): array
    {
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

        return [
            'free' => $consumedFree,
            'paid' => $consumedPaid,
        ];
    }

    /**
     * 有効期限切れのバランスを削除
     * 
     * @param \NexusPersistence\Support\CustomCollection $expiredBalanceCollection 期限切れバランスコレクション
     * @return array{total: int, free: int, paid: int} 削除された総数/無償/有償
     */
    private function removeExpiredBalances($expiredBalanceCollection): array
    {
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

        return [
            'total' => $totalExpired,
            'free' => $expiredFree,
            'paid' => $expiredPaid,
        ];
    }
}
