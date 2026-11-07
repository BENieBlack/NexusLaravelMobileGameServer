<?php

namespace NexusWallet\Repositories;

use NexusWallet\DataTransferObjects\WalletAmount;

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
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @return WalletAmount|null
     */
    public function selectByCurrencyId(int $playerId, string $currencyId): ?WalletAmount;

    /**
     * 通貨現在値を保存（INSERT or UPDATE）
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @param  int  $freeAmount  無償通貨数
     * @param  int  $paidAmount  有償通貨数
     * @return void
     */
    public function persist(int $playerId, string $currencyId, int $freeAmount, int $paidAmount): void;
}
