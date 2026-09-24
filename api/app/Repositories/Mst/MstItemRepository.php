<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstItem;
use Nexus\Core\Support\CustomCollection;

/**
 * MstItemRepository
 *
 * @extends _BaseMstRepository<MstItem>
 */
class MstItemRepository extends _BaseMstRepository
{
    protected string $modelClass = MstItem::class;

    /**
     * Wallet管理のアイテムかどうか
     *
     * 付与・消費の振り分けに使う。マスターに無いIDはWallet管理ではない
     * （通常アイテムとして trx_item で扱う）。
     */
    public function isWalletManaged(string $mstItemId): bool
    {
        return $this->selectById($mstItemId)?->isWallet() ?? false;
    }

    /**
     * Wallet管理のアイテムを全て取得
     *
     * trx_item から trx_wallet へ移す対象を洗い出すのに使う。
     *
     * @return CustomCollection<array-key, MstItem>
     */
    public function selectWalletManaged(): CustomCollection
    {
        return $this->queryOrMemory()
            ->where('is_wallet', true)
            ->values();
    }
}
