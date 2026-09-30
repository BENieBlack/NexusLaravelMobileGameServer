<?php

namespace App\Repositories\Trx;

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use NexusPitr\Logger\ShardMapper;

/**
 * ガチャ実行ログRepository。履歴はtrxへ保存せずlogへINSERTする。
 */
class TrxGachaHistoryRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function insert(array $data): void
    {
        $trxConnection = ApiSession::resolveConnectionName('trx');

        DB::connection(ShardMapper::resolveLogConnection($trxConnection))
            ->table('log_action_gacha_draw')
            ->insert([
                'unique_request_id' => $data['unique_request_id'],
                'sys_player_id' => $data['sys_player_id'],
                'mst_gacha_id' => $data['mst_gacha_id'],
                'draw_count' => $data['draw_count'],
                'cost_type' => $data['cost_type'],
                'cost_mst_id' => $data['cost_mst_id'],
                'cost_amount' => $data['cost_amount'],
                'result' => json_encode($data['prizes'], JSON_UNESCAPED_UNICODE),
                'system_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
