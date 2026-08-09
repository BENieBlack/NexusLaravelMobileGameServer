<?php

namespace LaravelWallet\Repositories;

/**
 * Wallet Repository Interface
 *
 * trx_wallet テーブルへのアクセスを抽象化
 */
interface WalletRepositoryInterface
{
    /**
     * 通貨IDで現在値を取得
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return object|null { free_amount: int, paid_amount: int, total_amount: int } または null
     */
    public function findByCurrencyId(int $playerId, string $currencyId): ?object;

    /**
     * 通貨現在値を保存（INSERT or UPDATE）
     *
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param int $freeAmount 無償通貨数
     * @param int $paidAmount 有償通貨数
     * @return void
     */
    public function save(int $playerId, string $currencyId, int $freeAmount, int $paidAmount): void;
}
