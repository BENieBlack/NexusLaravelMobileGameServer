<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxPlayer;

/**
 * TrxPlayerRepository
 *
 * プレイヤートランザクションデータ管理Repository
 * データアクセスのみを担当し、ビジネスロジックはServiceに委譲
 *
 * @extends _BaseTrxRepository<TrxPlayer>
 */
class TrxPlayerRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxPlayer::class;
}
