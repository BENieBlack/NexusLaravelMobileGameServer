<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxWallet;
use NexusWallet\DataTransferObjects\WalletAmount;
use NexusWallet\Repositories\WalletRepositoryInterface;

/**
 * WalletRepositoryAdapter
 *
 * nexus-walletパッケージのWalletRepositoryInterfaceを実装
 * Application層のTrxWalletRepositoryをラップ
 */
class WalletRepositoryAdapter implements WalletRepositoryInterface
{
    public function __construct(
        private readonly TrxWalletRepository $trxWalletRepository,
    ) {}

    /**
     * 通貨IDで現在値を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @return WalletAmount|null
     */
    public function selectByCurrencyId(int $playerId, string $currencyId): ?WalletAmount
    {
        $trxWallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

        if ($trxWallet === null) {
            return null;
        }

        return new WalletAmount(
            freeAmount: $trxWallet->getFreeAmount(),
            paidAmount: $trxWallet->getPaidAmount(),
        );
    }

    /**
     * 通貨現在値を保存（INSERT or UPDATE）
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @param  int  $freeAmount  無償通貨数
     * @param  int  $paidAmount  有償通貨数
     */
    public function persist(int $playerId, string $currencyId, int $freeAmount, int $paidAmount): void
    {
        $trxWallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

        if ($trxWallet === null) {
            $trxWallet = new TrxWallet([
                'sys_player_id' => $playerId,
                'mst_item_id' => $currencyId,
                'free_amount' => $freeAmount,
                'paid_amount' => $paidAmount,
            ]);
            $trxWallet->exists = false; // INSERT として認識
        } else {
            $trxWallet->setFreeAmount($freeAmount);
            $trxWallet->setPaidAmount($paidAmount);
        }

        $this->trxWalletRepository->setModel($trxWallet);
    }
}
