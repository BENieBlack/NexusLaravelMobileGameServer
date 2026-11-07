<?php

namespace NexusWallet\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusWallet\DataTransferObjects\WalletBalanceEntry;
use NexusWallet\Exceptions\InsufficientBalanceException;
use NexusWallet\Repositories\WalletBalanceRepositoryInterface;
use NexusWallet\Repositories\WalletRepositoryInterface;
use NexusWallet\ValueObjects\CurrencyBalance;
use NexusWallet\ValueObjects\CurrencyOperationResult;

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
     * 有効期限が切れた残高は含めない。期限切れの行の削除は、書き込みの経路（消費）で行う。
     * 読み取りだけの経路でも呼ばれるので、ここでは何も書き込まず、現在値から期限切れの分を引くだけにする。
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @return CurrencyBalance 残高情報
     */
    public function findBalance(int $playerId, string $currencyId): CurrencyBalance
    {
        $wallet = $this->walletRepository->selectByCurrencyId($playerId, $currencyId);

        if ($wallet === null) {
            return CurrencyBalance::zero();
        }

        $expired = $this->summarizeBalances(
            $this->walletBalanceRepository->selectAllExpiredByCurrencyId($playerId, $currencyId, ClockUtility::nowToString())
        );

        // 合計値はCurrencyBalanceが内訳から算出するため渡さない
        return new CurrencyBalance(
            freeAmount: max(0, $wallet->getFreeAmount() - $expired['free']),
            paidAmount: max(0, $wallet->getPaidAmount() - $expired['paid']),
        );
    }

    /**
     * 通貨を加算
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID（例: "gold", "event_coin"）
     * @param  int  $freeAmount  無償通貨数（デフォルト: 0）
     * @param  int  $paidAmount  有償通貨数（デフォルト: 0）
     * @param  string|null  $expireAt  有効期限 (Y-m-d H:i:s)（NULLの場合は無期限）
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

        $newFreeAmount = ($wallet?->getFreeAmount() ?? 0) + $freeAmount;
        $newPaidAmount = ($wallet?->getPaidAmount() ?? 0) + $paidAmount;

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
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @param  int  $amount  消費する数量
     * @return CurrencyOperationResult 操作結果
     *
     * @throws InsufficientBalanceException 残高不足の場合
     */
    public function consumeCurrency(
        int $playerId,
        string $currencyId,
        int $amount
    ): CurrencyOperationResult {
        // 1. 現在値を取得し、期限切れの分を除いた残高で判定する
        //    （現在値 total_amount は期限切れの行を含んだままなので、そのまま使うと期限切れの分も消費できてしまう）
        $wallet = $this->walletRepository->selectByCurrencyId($playerId, $currencyId);
        $expiredBalances = iterator_to_array(
            $this->walletBalanceRepository->selectAllExpiredByCurrencyId($playerId, $currencyId, ClockUtility::nowToString()),
            false
        );
        $expired = $this->summarizeBalances($expiredBalances);

        $availableFree = max(0, ($wallet?->getFreeAmount() ?? 0) - $expired['free']);
        $availablePaid = max(0, ($wallet?->getPaidAmount() ?? 0) - $expired['paid']);

        if ($wallet === null || $availableFree + $availablePaid < $amount) {
            throw new InsufficientBalanceException($currencyId, $amount, $availableFree + $availablePaid);
        }

        // 2. 期限切れの行を削除する（書き込みの経路なので、ここで片付ける）
        $this->removeExpiredBalances($expiredBalances);

        // 3. FIFO順で残高を取得（有償優先 → 有効期限が近いものから → ID順）。期限切れの行は除く
        $balances = $this->excludeExpired(
            $this->walletBalanceRepository->selectAllByCurrencyIdFifoOrder($playerId, $currencyId)
        );

        // 4. FIFO順で消費
        $consumedAmounts = $this->consumeFromBalances($balances, $amount);

        // 5. 現在値を減算（期限切れの分を除いた値から引く）
        $newFreeAmount = $availableFree - $consumedAmounts['free'];
        $newPaidAmount = $availablePaid - $consumedAmounts['paid'];
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
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @param  string  $currentTime  現在時刻 (Y-m-d H:i:s)
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
                $newFreeAmount = max(0, $wallet->getFreeAmount() - $expiredAmounts['free']);
                $newPaidAmount = max(0, $wallet->getPaidAmount() - $expiredAmounts['paid']);
                $this->walletRepository->persist($playerId, $currencyId, $newFreeAmount, $newPaidAmount);
            }
        }

        return $expiredAmounts['total'];
    }

    /**
     * 複数通貨の残高を一括取得
     *
     * @param  int  $playerId  プレイヤーID
     * @param  array<string>  $currencyIds  通貨IDリスト
     * @return array<string, CurrencyBalance> 通貨ID => 残高情報のマップ
     */
    public function findBulkBalances(int $playerId, array $currencyIds): array
    {
        $result = [];
        foreach ($currencyIds as $currencyId) {
            $result[$currencyId] = $this->findBalance($playerId, $currencyId);
        }

        return $result;
    }

    // ========================================
    // Private Methods
    // ========================================

    /**
     * バランスの数量を無償/有償に分けて合計する
     *
     * @param  iterable<WalletBalanceEntry>  $balances  バランスコレクション
     * @return array{free: int, paid: int}
     */
    private function summarizeBalances(iterable $balances): array
    {
        $free = 0;
        $paid = 0;

        foreach ($balances as $balance) {
            if ($balance->getIsPaid()) {
                $paid += $balance->getCurrentAmount();
            } else {
                $free += $balance->getCurrentAmount();
            }
        }

        return ['free' => $free, 'paid' => $paid];
    }

    /**
     * 有効期限が切れたバランスを除く
     *
     * @param  iterable<WalletBalanceEntry>  $balances  バランスコレクション
     * @return iterable<WalletBalanceEntry>
     */
    private function excludeExpired(iterable $balances): iterable
    {
        foreach ($balances as $balance) {
            if (! ClockUtility::isPast($balance->getExpireAt())) {
                yield $balance;
            }
        }
    }

    /**
     * バランスから通貨を消費（FIFO）
     *
     * @param  iterable<WalletBalanceEntry>  $balances  バランスコレクション
     * @param  int  $amount  消費する数量
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

            $consumeFromBalance = min($balance->getCurrentAmount(), $remainingAmount);
            $newAmount = $balance->getCurrentAmount() - $consumeFromBalance;
            $this->walletBalanceRepository->updateAmount($balance->getId(), $newAmount);

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
     * @param  iterable<WalletBalanceEntry>  $expiredBalances  期限切れバランスコレクション
     * @return array{total: int, free: int, paid: int} 削除された総数/無償/有償
     */
    private function removeExpiredBalances(iterable $expiredBalances): array
    {
        $totalExpired = 0;
        $expiredFree = 0;
        $expiredPaid = 0;

        foreach ($expiredBalances as $balance) {
            $expiredAmount = $balance->getCurrentAmount();
            $totalExpired += $expiredAmount;

            // 無償/有償の期限切れ数を記録
            if ($balance->getIsPaid()) {
                $expiredPaid += $expiredAmount;
            } else {
                $expiredFree += $expiredAmount;
            }

            $this->walletBalanceRepository->delete($balance->getId());
        }

        return [
            'total' => $totalExpired,
            'free' => $expiredFree,
            'paid' => $expiredPaid,
        ];
    }
}
