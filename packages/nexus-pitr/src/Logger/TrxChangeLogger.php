<?php

namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NexusPitr\Dto\ChangeLogDto;

/**
 * TrxChangeLogger
 * 
 * TrxDBの変更履歴をLogDBに記録する
 * トランザクション内で実行され、TrxDBとLogDBの整合性を保証
 */
class TrxChangeLogger
{
    /**
     * バッチログ記録（トランザクション内で実行）
     * 
     * @param array<ChangeLogDto> $changeLogDtos
     * @return void
     */
    public function logBatch(array $changeLogDtos): void
    {
        if (empty($changeLogDtos)) {
            return;
        }

        // シャード毎にグループ化
        $groupedByLog = [];
        foreach ($changeLogDtos as $dto) {
            $trxConn = $dto->getShardConnection();
            
            try {
                $logConn = ShardMapper::getLogConnection($trxConn);
            } catch (\InvalidArgumentException $e) {
                \Log::error('Invalid shard connection in PITR log', [
                    'trx_connection' => $trxConn,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
            
            if (!isset($groupedByLog[$logConn])) {
                $groupedByLog[$logConn] = [];
            }
            $groupedByLog[$logConn][] = $dto;
        }
        
        // LogDBシャード毎に書き込み
        foreach ($groupedByLog as $logConn => $dtos) {
            $this->insertToLogDb($logConn, $dtos);
        }
    }
    
    /**
     * 単一ログ記録（テスト用）
     * 
     * @param ChangeLogDto $changeLogDto
     * @return void
     */
    public function log(ChangeLogDto $changeLogDto): void
    {
        $this->logBatch([$changeLogDto]);
    }
    
    /**
     * LogDBにINSERT実行
     * 
     * @param string $logConnection
     * @param array<ChangeLogDto> $dtos
     * @return void
     */
    private function insertToLogDb(string $logConnection, array $dtos): void
    {
        $records = [];
        
        foreach ($dtos as $dto) {
            $records[] = [
                'id' => Str::uuid()->toString(), // UUIDv4（将来UUIDv7に変更推奨）
                'unique_request_id' => $dto->getUniqueRequestId(),
                'sys_player_id' => $dto->getSysPlayerId(),
                'shard_connection' => $dto->getShardConnection(),
                'table_name' => $dto->getTableName(),
                'operation' => $dto->getOperation(),
                'before_data' => $this->encodeJsonData($dto->getBeforeData()),
                'after_data' => $this->encodeJsonData($dto->getAfterData()),
                'primary_key' => json_encode($dto->getPrimaryKey(), JSON_UNESCAPED_UNICODE),
                'system_at' => $dto->getSystemAt()->format('Y-m-d H:i:s'),
                'api_endpoint' => $dto->getApiEndpoint(),
                'stack_trace' => $this->encodeJsonData($dto->getStackTrace()),
            ];
        }
        
        // ✅ 同一トランザクション内でINSERT
        // バッチサイズで分割（大量データ対策）
        $batchSize = config('nexus-pitr.batch_size', 1000);
        
        foreach (array_chunk($records, $batchSize) as $batch) {
            DB::connection($logConnection)->table('log_trx_change')->insert($batch);
        }
    }
    
    /**
     * JSONエンコード（nullセーフ）
     * 
     * @param array|null $data
     * @return string|null
     */
    private function encodeJsonData(?array $data): ?string
    {
        if ($data === null) {
            return null;
        }
        
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
