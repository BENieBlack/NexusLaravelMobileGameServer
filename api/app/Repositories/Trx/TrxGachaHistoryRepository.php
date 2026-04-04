<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxGachaHistory;

/**
 * TrxGachaHistoryRepository
 *
 * ガチャ実行履歴Repository
 * 
 * @extends _BaseTrxRepository<TrxGachaHistory>
 */
class TrxGachaHistoryRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxGachaHistory::class;
}
