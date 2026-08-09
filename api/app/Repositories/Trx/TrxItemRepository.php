<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxItem;

/**
 * TrxItemRepository
 *
 * アイテム管理Repository
 * データアクセスのみを担当し、ビジネスロジックはServiceに委譲
 *
 * @extends _BaseTrxRepository<TrxItem>
 */
class TrxItemRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxItem::class;

    /**
     * ユニークキー（sys_player_id, mst_item_id の複合キー）
     *
     * @var array<string>
     */
    protected array $uniqueKeys = ['sys_player_id', 'mst_item_id'];
}
