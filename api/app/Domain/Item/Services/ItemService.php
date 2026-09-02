<?php

namespace App\Domain\Item\Services;

use App\Domain\Item\Support\WalletItemMigrator;
use App\Models\Trx\TrxItem;
use App\Repositories\Mst\MstItemRepository;
use NexusResource\Services\ItemService as PackageItemService;
use NexusWallet\Services\WalletService;

/**
 * ItemService (Domain層ラッパー)
 *
 * パッケージ層のItemサービスをラップし、DTO ↔ Model変換を担当する。
 *
 * mst_item.is_wallet が立っているアイテムは残高として持つため、
 * trx_item ではなく Wallet（trx_wallet + trx_wallet_balance）へ振り分ける。
 * 振り分けをここに集約しているので、呼び出し側は
 * アイテムか残高かを意識しなくてよい。
 *
 * リリース後に is_wallet を立てた場合、trx_item に残っている残高は
 * WalletItemMigrator が触られた時点で Wallet へ移す。
 * そのため切り替えにメンテナンスは要らない。
 *
 * Note: ビジネスロジックはパッケージ層（NexusResource\Services\ItemService /
 * NexusWallet\Services\WalletService）に存在する。
 */
class ItemService
{
    public function __construct(
        private readonly PackageItemService $packageItemService,
        private readonly WalletService $walletService,
        private readonly MstItemRepository $mstItemRepository,
        private readonly WalletItemMigrator $walletItemMigrator,
    ) {}

    /**
     * アイテムを加算（既存の場合は加算、新規の場合は作成）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @param  int  $freeAmount  無償アイテム数（デフォルト: 0）
     * @param  int  $paidAmount  有償アイテム数（デフォルト: 0）
     * @param  string|null  $expireAt  有効期限（Wallet管理のみ。nullなら無期限）
     */
    public function addItem(
        int $sysPlayerId,
        string $mstItemId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?string $expireAt = null,
    ): void {
        if ($this->isWalletManaged($mstItemId)) {
            $this->walletItemMigrator->migrate($sysPlayerId, $mstItemId);
            $this->walletService->addCurrency($sysPlayerId, $mstItemId, $freeAmount, $paidAmount, $expireAt);

            return;
        }

        $this->packageItemService->addItem($sysPlayerId, $mstItemId, $freeAmount, $paidAmount);
    }

    /**
     * アイテムを消費（減算）
     * 有償アイテムから優先的に消費し、不足分は無償アイテムから消費する
     *
     * Wallet管理のアイテムは有効期限の近いものから消費する（先入先出）。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  mst_item.id
     * @param  int  $amount  消費する数量
     * @return TrxItem 消費後のアイテムデータ
     *
     * @throws \Exception 所持数が不足している場合
     */
    public function consumeItem(int $sysPlayerId, string $mstItemId, int $amount): TrxItem
    {
        if ($this->isWalletManaged($mstItemId)) {
            $this->walletItemMigrator->migrate($sysPlayerId, $mstItemId);

            return $this->consumeFromWallet($sysPlayerId, $mstItemId, $amount);
        }

        $item = $this->packageItemService->consumeItem($sysPlayerId, $mstItemId, $amount);

        // DTOからTrxItemを再取得（既存の利用箇所との互換性のため）
        // Note: 将来的にはDTOを直接返すようにリファクタリング推奨
        $trxItem = TrxItem::query()
            ->where('sys_player_id', $item->getSysPlayerId())
            ->where('mst_item_id', $item->getMstItemId())
            ->first();

        if (! $trxItem) {
            throw new \Exception("Item not found after consumption: {$mstItemId}");
        }

        // 消費結果はUnitOfWorkのキューに積まれただけでDBには未反映のため、
        // 再取得したモデルにはDTOの最新値を反映して返す
        $trxItem->setFreeAmount($item->getFreeAmount());
        $trxItem->setPaidAmount($item->getPaidAmount());

        return $trxItem;
    }

    /**
     * アイテムの所持数を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  アイテムID
     * @return int 所持数（無償+有償の合計、存在しない場合は0）
     */
    public function findItemAmount(int $sysPlayerId, string $mstItemId): int
    {
        if ($this->isWalletManaged($mstItemId)) {
            $this->walletItemMigrator->migrate($sysPlayerId, $mstItemId);

            return $this->walletService->findBalance($sysPlayerId, $mstItemId)->getTotalAmount();
        }

        return $this->packageItemService->findItemAmount($sysPlayerId, $mstItemId);
    }

    /**
     * Wallet管理のアイテムかどうか
     */
    public function isWalletManaged(string $mstItemId): bool
    {
        return $this->mstItemRepository->isWalletManaged($mstItemId);
    }

    /**
     * Walletから消費し、呼び出し側が期待する形へ詰め替える
     *
     * Wallet管理のアイテムに trx_item の行は無い。戻り値の型を保つため、
     * 消費後の残高を載せた保存しないモデルを返す。
     */
    private function consumeFromWallet(int $sysPlayerId, string $mstItemId, int $amount): TrxItem
    {
        $this->walletService->consumeCurrency($sysPlayerId, $mstItemId, $amount);

        $balance = $this->walletService->findBalance($sysPlayerId, $mstItemId);

        $trxItem = new TrxItem;
        $trxItem->setSysPlayerId($sysPlayerId);
        $trxItem->setMstItemId($mstItemId);
        $trxItem->setFreeAmount($balance->getFreeAmount());
        $trxItem->setPaidAmount($balance->getPaidAmount());

        return $trxItem;
    }
}
