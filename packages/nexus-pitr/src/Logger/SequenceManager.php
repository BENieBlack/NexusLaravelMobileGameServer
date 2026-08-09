<?php

namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;

class SequenceManager
{
    /**
     * 次のシーケンス番号を取得（アトミック）
     */
    public function getNextSequence(string $shardConnection): int
    {
        return DB::connection('log')->transaction(function () use ($shardConnection) {
            $row = DB::connection('log')
                ->table('log_trx_sequence')
                ->where('shard_connection', $shardConnection)
                ->lockForUpdate()
                ->first();

            $nextSeq = ($row?->current_sequence ?? 0) + 1;

            DB::connection('log')
                ->table('log_trx_sequence')
                ->updateOrInsert(
                    ['shard_connection' => $shardConnection],
                    ['current_sequence' => $nextSeq]
                );

            return $nextSeq;
        });
    }

    /**
     * 複数シーケンス番号を一括予約（バッチ処理用）
     */
    public function reserveSequences(string $shardConnection, int $count): int
    {
        return DB::connection('log')->transaction(function () use ($shardConnection, $count) {
            $row = DB::connection('log')
                ->table('log_trx_sequence')
                ->where('shard_connection', $shardConnection)
                ->lockForUpdate()
                ->first();

            $baseSeq = ($row?->current_sequence ?? 0) + 1;
            $newSeq = $baseSeq + $count - 1;

            DB::connection('log')
                ->table('log_trx_sequence')
                ->updateOrInsert(
                    ['shard_connection' => $shardConnection],
                    ['current_sequence' => $newSeq]
                );

            return $baseSeq;
        });
    }
}
