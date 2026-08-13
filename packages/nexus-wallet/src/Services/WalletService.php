<?php

namespace LaravelWallet\Services;

use LaravelWallet\ValueObjects\CurrencyBalance;
use LaravelWallet\ValueObjects\CurrencyOperationResult;
use LaravelWallet\Exceptions\InsufficientBalanceException;
use LaravelWallet\Repositories\WalletBalanceRepositoryInterface;
use LaravelWallet\Repositories\WalletRepositoryInterface;

/**
 * WalletService
 *
 * 仮想通貨管理の核となるサービス
 * FIFO方式（有償優先）で通貨の増減を管理
 */
class WalletService
{
    public function __construct(
        private readonly WalletRepositoryInterface $walletRepository,
        private readonly WalletBalanceRepositoryInterface $walletBalanceRepository,
    ) {}

    /**
     * 通貨残高を取得
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return CurrencyBalance 残高情報
     */
    public function getBalance(int $playerId, string $currencyId): CurrencyBalance
    {
        $wallet = $this->walletRepository->selectByCurrencyId($playerId, $currencyId);

        if ($wallet === null) {
            return CurrencyBalance::zero();
        }

        // 合計値はCurrencyBalanceが内訳から算出するため渡さない
        return new CurrencyBalance(
            freeAmount: $wallet->free_amount,
            paidAmount: $wallet->paid_amount,
        );
    }

    /**
     * 通貨を加算
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID（例: "gold", "event_coin"）
     * @param int $freeAmount 無償通貨数（デフォルト: 0）
     * @param int $paidAmount 有償通貨数（デフォルト: 0）
     * @param string|null $expireAt 有効期限 (Y-m-d H:i:s)（NULLの場合は無期限）
     * @return CurrencyOperationResult 操作結果
     */
    public function addCurrency(
        int $playerId,
        string $currencyId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?string $expireAt = null
    ): CurrencyOperationResult {
        // 1. 現在値を取得または新規作成
        $wallet = $this->walletRepository->selectByCurrencyId($playerId, $currencyId);

        $newFreeAmount = ($wallet->free_amount ?? 0) + $freeAmount;
        $newPaidAmount = ($wallet->paid_amount ?? 0) + $paidAmount;

        // 2. 現在値を更新
        $this->walletRepository->persist($playerId, $currencyId, $newFreeAmount, $newPaidAmount);

        // 3. 無償通貨の残高レコード追加（FIFO用）
        if ($freeAmount > 0) {
            $this->walletBalanceRepository->insert($playerId, $currencyId, $freeAmount, isPaid: false, expireAt: $expireAt);
        }

        // 4. 有償通貨の残高レコード追加（FIFO用）
        if ($paidAmount > 0) {
            $this->walletBalanceRepository->insert($playerId, $currencyId, $paidAmount, isPaid: true, expireAt: $expireAt);
        }

        return new CurrencyOperationResult(
            freeAmount: $freeAmount,
            paidAmount: $paidAmount,
            currentBalance: $newFreeAmount + $newPaidAmount,
        );
    }

    /**
     * 通貨を消費（FIFO方式、有償優先）
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param int $amount 消費する数量
     * @return CurrencyOperationResult 操作結果
     *
     * @throws InsufficientBalanceException 残高不足の場合
     */
    public function consumeCurrency(
        int $playerId,
        string $currencyId,
        int $amount
    ): CurrencyOperationResult {
        // 1. 現在値を取得
        $wallet = $this->walletRepository->selectByCurrencyId($playerId, $currencyId);

        if ($wallet === null || $wallet->total_amount < $amount) {
            $available = $wallet?->total_amount ?? 0;
            throw new InsufficientBalanceException($currencyId, $amount, $available);
        }

        // 2. FIFO順で残高を取得（有償優先 → 有効期限が近いものから → ID順）
        $balances = $this->walletBalanceRepository->selectAllByCurrencyIdFifoOrder($playerId, $currencyId);

        // 3. FIFO順で消費
        $consumedAmounts = $this->consumeFromBalances($balances, $amount);

        // 4. 現在値を減算
        $newFreeAmount = $wallet->free_amount - $consumedAmounts['free'];
        $newPaidAmount = $wallet->paid_amount - $consumedAmounts['paid'];
        $this->walletRepository->persist($playerId, $currencyId, $newFreeAmount, $newPaidAmount);

        return new CurrencyOperationResult(
            freeAmount: $consumedAmounts['free'],
            paidAmount: $consumedAmounts['paid'],
            currentBalance: $newFreeAmount + $newPaidAmount,
        );
    }

    /**
     * 有効期限切れの通貨を削除
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param string $currentTime 現在時刻 (Y-m-d H:i:s)
     * @return int 削除された数量
     */
    public function removeExpiredCurrency(int $playerId, string $currencyId, string $currentTime): int
    {
        // 有効期限切れの残高を取得
        $expiredBalances = $this->walletBalanceRepository->selectAllExpiredByCurrencyId($playerId, $currencyId, $currentTime);

        $expiredAmounts = $this->removeExpiredBalances($expiredBalances);

        // 現在値から減算
        if ($expiredAmounts['total'] > 0) {
            $wallet = $this->walletRepository->selectByCurrencyId($playerId, $currencyId);

            if ($wallet !== null) {
                $newFreeAmount = max(0, $wallet->free_amount - $expiredAmounts['free']);
                $newPaidAmount = max(0, $wallet->paid_amount - $expiredAmounts['paid']);
                $this->walletRepository->persist($playerId, $currencyId, $newFreeAmount, $newPaidAmount);
            }
        }

        return $expiredAmounts['total'];
    }

    /**
     * 複数通貨の残高を一括取得
     *
     * @param int $playerId プレイヤーID
     * @param array<string> $currencyIds 通貨IDリスト
     * @return array<string, CurrencyBalance> 通貨ID => 残高情報のマップ
     */
    public function getBulkBalances(int $playerId, array $currencyIds): array
    {
        $result = [];
        foreach ($currencyIds as $currencyId) {
            $result[$currencyId] = $this->getBalance($playerId, $currencyId);
        }

        return $result;
    }

    // ========================================
    // Private Methods
    // ========================================

    /**
     * バランスから通貨を消費（FIFO）
     *
     * @param iterable<object> $balances バランスコレクション
     * @param int $amount 消費する数量
     * @return array{free: int, paid: int} 消費した無償/有償の数量
     */
    private function consumeFromBalances(iterable $balances, int $amount): array
    {
        $remainingAmount = $amount;
        $consumedFree = 0;
        $consumedPaid = 0;

        foreach ($balances as $balance) {
            if ($remainingAmount <= 0) {
                break;
            }

            $consumeFromBalance = min($balance->current_amount, $remainingAmount);
            $newAmount = $balance->current_amount - $consumeFromBalance;
            $this->walletBalanceRepository->updateAmount($balance->id, $newAmount);

            // 無償/有償の消費数を記録
            if ($balance->is_paid) {
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
     * @param iterable<object> $expiredBalances 期限切れバランスコレクション
     * @return array{total: int, free: int, paid: int} 削除された総数/無償/有償
     */
    private function removeExpiredBalances(iterable $expiredBalances): array
    {
        $totalExpired = 0;
        $expiredFree = 0;
        $expiredPaid = 0;

        foreach ($expiredBalances as $balance) {
            $expiredAmount = $balance->current_amount;
            $totalExpired += $expiredAmount;

            // 無償/有償の期限切れ数を記録
            if ($balance->is_paid) {
                $expiredPaid += $expiredAmount;
            } else {
                $expiredFree += $expiredAmount;
            }

            $this->walletBalanceRepository->delete($balance->id);
        }

        return [
            'total' => $totalExpired,
            'free' => $expiredFree,
            'paid' => $expiredPaid,
        ];
    }
}
