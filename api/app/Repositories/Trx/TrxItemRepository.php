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
}
