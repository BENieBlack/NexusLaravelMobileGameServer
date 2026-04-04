<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxDiamondBalance;

/**
 * TrxDiamondBalanceRepository
 *
 * ダイヤモンド残高管理Repository（購入単位）
 * 先入先出（FIFO）方式で消費し、返金計算を可能にする
 * 
 * @extends _BaseTrxRepository<TrxDiamondBalance>
 */
class TrxDiamondBalanceRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxDiamondBalance::class;
}
