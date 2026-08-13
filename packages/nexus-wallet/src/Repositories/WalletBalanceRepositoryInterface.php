<?php

namespace LaravelWallet\Repositories;

/**
 * WalletBalance Repository Interface
 *
 * trx_wallet_balance テーブルへのアクセスを抽象化
 * FIFO消費のための残高明細を管理
 */
interface WalletBalanceRepositoryInterface
{
    /**
     * FIFO順で残高レコードを取得
     *
     * 優先順位: is_paid DESC (有償優先) → expire_at ASC (有効期限が近いものから) → id ASC
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return iterable<object> { id: int, is_paid: bool, current_amount: int, initial_amount: int, expire_at: ?string }
     */
    public function selectAllByCurrencyIdFifoOrder(int $playerId, string $currencyId): iterable;

    /**
     * 有効期限切れの残高レコードを取得
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param string $currentTime 現在時刻 (Y-m-d H:i:s)
     * @return iterable<object> { id: int, is_paid: bool, current_amount: int }
     */
    public function selectAllExpiredByCurrencyId(int $playerId, string $currencyId, string $currentTime): iterable;

    /**
     * 残高レコードを作成
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param int $amount 数量
     * @param bool $isPaid 有償フラグ
     * @param string|null $expireAt 有効期限 (Y-m-d H:i:s)、NULLの場合は無期限
     * @return void
     */
    public function insert(int $playerId, string $currencyId, int $amount, bool $isPaid, ?string $expireAt): void;

    /**
     * 残高レコードの現在数量を更新
     *
     * @param int $balanceId 残高レコードID
     * @param int $newAmount 新しい数量
     * @return void
     */
    public function updateAmount(int $balanceId, int $newAmount): void;

    /**
     * 残高レコードを論理削除
     *
     * @param int $balanceId 残高レコードID
     * @return void
     */
    public function delete(int $balanceId): void;
}
