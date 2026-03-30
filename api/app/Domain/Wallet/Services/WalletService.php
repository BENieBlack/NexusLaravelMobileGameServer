<?php

namespace App\Domain\Wallet\Services;

use App\Models\Trx\TrxWallet;
use App\Models\Trx\TrxWalletBalance;
use App\Repositories\Trx\TrxWalletBalanceRepository;
use App\Repositories\Trx\TrxWalletRepository;
use App\Utilities\ApiSession;
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
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * 通貨を加算
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId 通貨アイテムID（例: "gold", "event_coin"）
     * @param int $amount 加算する数量
     * @param CarbonImmutable|null $expireAt 有効期限（NULLの場合は無期限）
     * @return array{amount: int, total_amount: int}
     */
    public function addCurrency(
        int $sysPlayerId,
        string $mstItemId,
        int $amount,
        ?CarbonImmutable $expireAt = null
    ): array {
        
        // 1. 現在値を取得または作成（Repository経由）
        $wallet = $this->trxWalletRepository->selectByMstItemId($sysPlayerId, $mstItemId);

        if ($wallet === null) {
            $wallet = new TrxWallet([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'amount' => 0,
            ]);
            $wallet->exists = false; // INSERT として認識
        }

        // 2. 通貨加算
        $wallet->setAmount($wallet->getAmount() + $amount);
        $this->trxWalletRepository->setModel($wallet);

        // 3. 残高レコード追加（FIFO用）
        $balance = new TrxWalletBalance([
            'sys_player_id' => $sysPlayerId,
            'mst_item_id' => $mstItemId,
            'current_amount' => $amount,
            'initial_amount' => $amount,
            'expire_at' => $expireAt,
        ]);
        $balance->exists = false; // INSERT として認識
        $this->trxWalletBalanceRepository->setModel($balance);

        return [
            'amount' => $amount,
            'total_amount' => $wallet->getAmount(),
        ];
    }

    /**
     * 通貨を消費（FIFO方式）
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

        if ($wallet === null || $wallet->getAmount() < $amount) {
            throw new \Exception("Insufficient currency: {$mstItemId}");
        }

        // 2. FIFO順で残高を取得（Repository経由）
        $balanceCollection = $this->trxWalletBalanceRepository->findAllBalancesByMstItemId($mstItemId);

        // 3. FIFO順で消費
        $remainingAmount = $amount;
        foreach ($balanceCollection as $balance) {
            if ($remainingAmount <= 0) {
                break;
            }

            $consumeFromBalance = min($balance->getCurrentAmount(), $remainingAmount);
            $balance->setCurrentAmount($balance->getCurrentAmount() - $consumeFromBalance);
            $this->trxWalletBalanceRepository->setModel($balance);

            $remainingAmount -= $consumeFromBalance;
        }

        // 4. 現在値を減算
        $wallet->setAmount($wallet->getAmount() - $amount);
        $this->trxWalletRepository->setModel($wallet);

        return [
            'consumed_amount' => $amount,
            'total_amount' => $wallet->getAmount(),
        ];
    }

    /**
     * 通貨残高を取得
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $mstItemId 通貨アイテムID
     * @return int 現在の残高
     */
    public function getBalance(int $sysPlayerId, string $mstItemId): int
    {
        // Repository経由で取得
        $wallet = $this->trxWalletRepository->selectByMstItemId($sysPlayerId, $mstItemId);

        return $wallet?->getAmount() ?? 0;
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
        $now = CarbonImmutable::now();

        // 有効期限切れの残高を取得（Repository経由）
        $expiredBalanceCollection = $this->trxWalletBalanceRepository->findAllExpiredBalancesByMstItemId($mstItemId, $now);

        $totalExpired = 0;
        foreach ($expiredBalanceCollection as $balance) {
            $totalExpired += $balance->getCurrentAmount();
            $balance->setCurrentAmount(0);
            $this->trxWalletBalanceRepository->setModel($balance);
        }

        // 現在値から減算
        if ($totalExpired > 0) {
            $wallet = $this->trxWalletRepository->selectByMstItemId($sysPlayerId, $mstItemId);

            if ($wallet !== null) {
                $wallet->setAmount(max(0, $wallet->getAmount() - $totalExpired));
                $this->trxWalletRepository->setModel($wallet);
            }
        }

        return $totalExpired;
    }
}
