<?php

namespace App\Domain\Wallet\Services;

use App\Models\Trx\TrxWallet;
use App\Models\Trx\TrxWalletBalance;
use App\Repositories\Trx\TrxWalletBalanceRepository;
use App\Repositories\Trx\TrxWalletRepository;
use NexusUtilities\ClockUtility;
use LaravelWallet\Contracts\WalletManagerInterface;
use LaravelWallet\DTOs\CurrencyBalanceDto;
use LaravelWallet\DTOs\CurrencyOperationResultDto;
use LaravelWallet\Exceptions\InsufficientBalanceException;

/**
 * WalletService
 * 
 * 汎用通貨の増減処理を担当するサービス
 * Gold, EventCoin, RaidMedal, PvPPoint等を統合管理
 */
class WalletService implements WalletManagerInterface
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
            $freeBalance = new TrxWalletBalance([
                'sys_player_id' => $playerId,
                'mst_item_id' => $currencyId,
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
                'sys_player_id' => $playerId,
                'mst_item_id' => $currencyId,
                'current_amount' => $paidAmount,
                'initial_amount' => $paidAmount,
                'expire_at' => $expireAt,
                'is_paid' => true,
            ]);
            $paidBalance->exists = false; // INSERT として認識
            $this->trxWalletBalanceRepository->setModel($paidBalance);
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

        return new CurrencyOperationResultDto(
            freeAmount: $consumedFree,
            paidAmount: $consumedPaid,
            totalAmount: $amount,
            currentBalance: $wallet->getTotalAmount(),
        );
    }

    /**
     * 通貨残高を取得
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return CurrencyBalanceDto 残高情報
     */
    public function getBalance(int $playerId, string $currencyId): CurrencyBalanceDto
    {
        // Repository経由で取得
        $wallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

        if ($wallet === null) {
            return new CurrencyBalanceDto(
                freeAmount: 0,
                paidAmount: 0,
                totalAmount: 0,
            );
        }

        return new CurrencyBalanceDto(
            freeAmount: $wallet->getFreeAmount(),
            paidAmount: $wallet->getPaidAmount(),
            totalAmount: $wallet->getTotalAmount(),
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
            $wallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

            if ($wallet !== null) {
                $wallet->setFreeAmount(max(0, $wallet->getFreeAmount() - $expiredFree));
                $wallet->setPaidAmount(max(0, $wallet->getPaidAmount() - $expiredPaid));
                $this->trxWalletRepository->setModel($wallet);
            }
        }

        return $totalExpired;
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
}
