# LogDBシャーディング設計書（動的シャーディング対応版）

## 概要

LogDBをTrxDBと同様に**動的シャーディング対応**し、スケーラビリティと復旧効率を向上させます。

**新機能**: `DB_TRX_SHARDS`環境変数でシャード数を制御し、手動のコード変更なしでスケール可能。

## シャーディング方針

### 基本原則

```
TrxDB : LogDB = 1 : 1 の対応関係（動的）
```

- **trx1** → **log1** (trx1の変更ログ専用)
- **trx2** → **log2** (trx2の変更ログ専用)
- **trx3** → **log3** (DB_TRX_SHARDS=3以上の場合)
- **trx4** → **log4** (DB_TRX_SHARDS=4以上の場合)
- ...（環境変数で自由に拡張可能）

### 利点

#### 1. スケーラビリティ

```
単一LogDB:
  書き込み: 全シャード分が集中 → ボトルネック
  読み込み: 復旧時に全ログをスキャン → 遅い

シャード化LogDB:
  書き込み: シャード毎に分散 → 並列化可能
  読み込み: 対象シャードのみクエリ → 高速
  
動的シャーディング:
  環境変数変更のみでシャード数を増減可能 → 運用柔軟性
```

#### 2. 障害影響範囲の隔離

```
単一LogDB障害:
  全TrxDBが復旧不可能 → サービス全体停止

シャード化LogDB障害:
  障害シャードのプレイヤーのみ影響 → 部分稼働可能
```

#### 3. 復旧の並列化

```
trx1とtrx2を同時復旧:
  log1とlog2から並列で復旧 → 復旧時間短縮
```

## DB構成

### 環境変数制御

```env
# シャード数を指定（デフォルト: 2）
DB_TRX_SHARDS=4
```

上記設定で自動的に以下が生成されます：
- trx1, trx2, trx3, trx4（TrxDB接続）
- log1, log2, log3, log4（LogDB接続）

### 物理構成

```
Docker Compose（DB_TRX_SHARDS=2の場合）:
  db-log1  (MySQL 8.4) ← trx1のログ専用
  db-log2  (MySQL 8.4) ← trx2のログ専用
  
Docker Compose（DB_TRX_SHARDS=4の場合）:
  db-log1  (MySQL 8.4) ← trx1のログ専用
  db-log2  (MySQL 8.4) ← trx2のログ専用
  db-log3  (MySQL 8.4) ← trx3のログ専用
  db-log4  (MySQL 8.4) ← trx4のログ専用
  
本番環境（DB_TRX_SHARDS=4の場合）:
  RDS log1 (Multi-AZ) ← trx1のログ専用
  RDS log2 (Multi-AZ) ← trx2のログ専用
  RDS log3 (Multi-AZ) ← trx3のログ専用
  RDS log4 (Multi-AZ) ← trx4のログ専用
```

### Laravel接続設定（動的生成）

```php
// config/database.php

'connections' => [
    // ========================================
    // 動的シャーディング: TrxDB
    // ========================================
    // DB_TRX_SHARDS環境変数でシャード数を指定（デフォルト: 2）
    // 例: DB_TRX_SHARDS=4 の場合、trx1, trx2, trx3, trx4 を生成
    ...array_merge(
        // 後方互換用: trx接続（trx1を参照）
        [
            'trx' => [
                'driver' => 'mysql',
                'host' => env('DB_TRX1_HOST', 'db-trx1'),
                'port' => env('DB_TRX1_PORT', '3306'),
                'database' => env('DB_TRX1_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-trx1',
                'username' => env('DB_TRX1_USERNAME', 'root'),
                'password' => env('DB_TRX1_PASSWORD', 'root'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
        ],
        // 動的生成: trx1, trx2, ...
        (function() {
            $shardCount = (int) env('DB_TRX_SHARDS', 2);
            $connections = [];
            
            for ($i = 1; $i <= $shardCount; $i++) {
                $connections["trx{$i}"] = [
                    'driver' => 'mysql',
                    'host' => env("DB_TRX{$i}_HOST", "db-trx{$i}"),
                    'port' => env("DB_TRX{$i}_PORT", '3306'),
                    'database' => env("DB_TRX{$i}_DATABASE") ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . "-trx{$i}",
                    'username' => env("DB_TRX{$i}_USERNAME", 'root'),
                    'password' => env("DB_TRX{$i}_PASSWORD", 'root'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ];
            }
            
            return $connections;
        })()
    ),

    // ========================================
    // 動的シャーディング: LogDB
    // ========================================
    // TrxDBと1:1対応でLogDBシャードを生成
    // DB_TRX_SHARDS=2 の場合、log1, log2 を生成
    ...array_merge(
        // 後方互換用: log接続（単一LogDB）
        [
            'log' => [
                'driver' => 'mysql',
                'host' => env('DB_LOG_HOST', '127.0.0.1'),
                'port' => env('DB_LOG_PORT', '63063'),
                'database' => env('DB_LOG_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-log',
                'username' => env('DB_LOG_USERNAME', 'root'),
                'password' => env('DB_LOG_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
        ],
        // 動的生成: log1, log2, ...
        (function() {
            $shardCount = (int) env('DB_TRX_SHARDS', 2);
            $connections = [];
            
            for ($i = 1; $i <= $shardCount; $i++) {
                $connections["log{$i}"] = [
                    'driver' => 'mysql',
                    'host' => env("DB_LOG{$i}_HOST", "db-log{$i}"),
                    'port' => env("DB_LOG{$i}_PORT", '3306'),
                    'database' => env("DB_LOG{$i}_DATABASE") ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . "-log{$i}",
                    'username' => env("DB_LOG{$i}_USERNAME", 'root'),
                    'password' => env("DB_LOG{$i}_PASSWORD", 'root'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ];
            }
            
            return $connections;
        })()
    ),
];

// PITR設定
'pitr' => [
    'shard_count' => (int) env('DB_TRX_SHARDS', 2),
    'active_trx_connections' => (function() {
        $shardCount = (int) env('DB_TRX_SHARDS', 2);
        $connections = [];
        for ($i = 1; $i <= $shardCount; $i++) {
            $connections[] = "trx{$i}";
        }
        return $connections;
    })(),
    'batch_size' => env('PITR_BATCH_SIZE', 1000),
    'enable_compression' => env('PITR_ENABLE_COMPRESSION', false),
],
```

### .env設定例（DB_TRX_SHARDS=4の場合）

```env
# シャード数指定
DB_TRX_SHARDS=4

# Log1 DB (trx1用)
DB_LOG1_HOST=db-log1
DB_LOG1_PORT=3306
DB_LOG1_DATABASE=nexus-local-log1
DB_LOG1_USERNAME=root
DB_LOG1_PASSWORD=root

# Log2 DB (trx2用)
DB_LOG2_HOST=db-log2
DB_LOG2_PORT=3306
DB_LOG2_DATABASE=nexus-local-log2
DB_LOG2_USERNAME=root
DB_LOG2_PASSWORD=root

# Log3 DB (trx3用)
DB_LOG3_HOST=db-log3
DB_LOG3_PORT=3306
DB_LOG3_DATABASE=nexus-local-log3
DB_LOG3_USERNAME=root
DB_LOG3_PASSWORD=root

# Log4 DB (trx4用)
DB_LOG4_HOST=db-log4
DB_LOG4_PORT=3306
DB_LOG4_DATABASE=nexus-local-log4
DB_LOG4_USERNAME=root
DB_LOG4_PASSWORD=root

# 共通Log DB (アクセスログ等)
DB_LOG_HOST=db-log
DB_LOG_PORT=3306
DB_LOG_DATABASE=nexus-local-log
DB_LOG_USERNAME=root
DB_LOG_PASSWORD=root
```

## テーブル配置戦略

### PITR専用ログ（シャーディング対象）

以下はTrxDBシャードに1:1対応でLogDBシャードに配置：

```
log1:
  - log_trx_change      (trx1の変更ログ)
  - log_trx_sequence    (trx1のシーケンス番号)
  - log_trx_checksum    (trx1のチェックサム)

log2:
  - log_trx_change      (trx2の変更ログ)
  - log_trx_sequence    (trx2のシーケンス番号)
  - log_trx_checksum    (trx2のチェックサム)
```

### 既存の共通ログ（シャーディング不要）

以下は単一`log`接続に残す（プレイヤーIDで横断検索が必要なため）：

```
log:
  - log_access           (全プレイヤーのアクセスログ)
  - log_player           (全プレイヤーのレベル変更ログ)
  - log_item             (全プレイヤーのアイテム変更ログ)
  - log_unit             (全プレイヤーのユニット変更ログ)
  - log_equipment        (全プレイヤーの装備変更ログ)
  - log_gacha            (全プレイヤーのガチャログ)
  - log_in_app_purchase  (全プレイヤーの課金ログ)
  - log_vip_point        (全プレイヤーのVIPポイント変更ログ)
```

**理由**: 運営管理画面でプレイヤーID横断検索が必要（例: 全プレイヤーのガチャ履歴分析）

## 実装詳細

### 1. TrxDBとLogDBのマッピング（動的シャーディング対応）

```php
// packages/nexus-pitr/src/Logger/ShardMapper.php

namespace NexusPitr\Logger;

/**
 * ShardMapper
 * 
 * TrxDB接続とLogDB接続のマッピングを管理
 * 動的シャーディング対応（DB_TRX_SHARDS環境変数でシャード数を制御）
 */
class ShardMapper
{
    /**
     * TrxDB接続名から対応するLogDB接続名を取得
     * 
     * @param string $trxConnection
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function getLogConnection(string $trxConnection): string
    {
        // 後方互換: trx -> log
        if ($trxConnection === 'trx') {
            return 'log';
        }
        
        // 動的シャーディング: trx1 -> log1, trx2 -> log2, ...
        if (preg_match('/^trx(\d+)$/', $trxConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();
            
            if ($shardNumber >= 1 && $shardNumber <= $maxShards) {
                return "log{$shardNumber}";
            }
        }
        
        throw new \InvalidArgumentException("Unknown trx connection: {$trxConnection}");
    }
    
    /**
     * LogDB接続名から対応するTrxDB接続名を取得
     * 
     * @param string $logConnection
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function getTrxConnection(string $logConnection): string
    {
        // 後方互換: log -> trx
        if ($logConnection === 'log') {
            return 'trx';
        }
        
        // 動的シャーディング: log1 -> trx1, log2 -> trx2, ...
        if (preg_match('/^log(\d+)$/', $logConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();
            
            if ($shardNumber >= 1 && $shardNumber <= $maxShards) {
                return "trx{$shardNumber}";
            }
        }
        
        throw new \InvalidArgumentException("Unknown log connection: {$logConnection}");
    }
    
    /**
     * すべてのLogDB接続名を取得
     * 
     * @return array<string>
     */
    public static function getAllLogConnections(): array
    {
        $maxShards = self::getMaxShardCount();
        $connections = [];
        
        for ($i = 1; $i <= $maxShards; $i++) {
            $connections[] = "log{$i}";
        }
        
        return $connections;
    }
    
    /**
     * すべてのTrxDB接続名を取得
     * 
     * @return array<string>
     */
    public static function getAllTrxConnections(): array
    {
        $maxShards = self::getMaxShardCount();
        $connections = [];
        
        for ($i = 1; $i <= $maxShards; $i++) {
            $connections[] = "trx{$i}";
        }
        
        return $connections;
    }
    
    /**
     * 指定されたTrxDB接続が有効かチェック
     * 
     * @param string $trxConnection
     * @return bool
     */
    public static function isValidTrxConnection(string $trxConnection): bool
    {
        // 後方互換
        if ($trxConnection === 'trx') {
            return true;
        }
        
        // 動的シャーディング
        if (preg_match('/^trx(\d+)$/', $trxConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();
            return $shardNumber >= 1 && $shardNumber <= $maxShards;
        }
        
        return false;
    }
    
    /**
     * 指定されたLogDB接続が有効かチェック
     * 
     * @param string $logConnection
     * @return bool
     */
    public static function isValidLogConnection(string $logConnection): bool
    {
        // 後方互換
        if ($logConnection === 'log') {
            return true;
        }
        
        // 動的シャーディング
        if (preg_match('/^log(\d+)$/', $logConnection, $matches)) {
            $shardNumber = (int) $matches[1];
            $maxShards = self::getMaxShardCount();
            return $shardNumber >= 1 && $shardNumber <= $maxShards;
        }
        
        return false;
    }
    
    /**
     * 最大シャード数を取得
     * 
     * @return int
     */
    private static function getMaxShardCount(): int
    {
        return (int) (getenv('DB_TRX_SHARDS') ?: 2);
    }
}
```

### 2. TrxChangeLogger修正（シャーディング対応）

```php
// nexus-pitr/src/Logger/TrxChangeLogger.php

namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;
use NexusPitr\DataTransferObjects\ChangeLog;

class TrxChangeLogger
{
    public function __construct(
        private readonly SequenceManager $sequenceManager
    ) {}

    /**
     * TrxDB変更をLogDBに記録（シャーディング対応）
     */
    public function log(ChangeLog $changeLogDto): void
    {
        $trxConnection = $changeLogDto->getShardConnection();
        $logConnection = ShardMapper::getLogConnection($trxConnection);
        
        $sequenceNumber = $this->sequenceManager->issueNextSequence(
            $trxConnection,
            $logConnection
        );

        DB::connection($logConnection)->table('log_trx_change')->insert([
            'unique_request_id' => $changeLogDto->getUniqueRequestId(),
            'sys_player_id' => $changeLogDto->getSysPlayerId(),
            'shard_connection' => $changeLogDto->getShardConnection(),
            'table_name' => $changeLogDto->getTableName(),
            'operation' => $changeLogDto->getOperation(),
            'before_data' => $changeLogDto->getBeforeData() ? json_encode($changeLogDto->getBeforeData()) : null,
            'after_data' => $changeLogDto->getAfterData() ? json_encode($changeLogDto->getAfterData()) : null,
            'primary_key' => json_encode($changeLogDto->getPrimaryKey()),
            'sequence_number' => $sequenceNumber,
            'system_at' => $changeLogDto->getSystemAt(),
            'api_endpoint' => $changeLogDto->getApiEndpoint(),
            'stack_trace' => $changeLogDto->getStackTrace() ? json_encode($changeLogDto->getStackTrace()) : null,
        ]);
    }
}
```

### 3. SequenceManager修正（シャーディング対応）

```php
// nexus-pitr/src/Logger/SequenceManager.php

namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;

class SequenceManager
{
    /**
     * 次のシーケンス番号を取得（シャーディング対応）
     */
    public function issueNextSequence(string $trxConnection, string $logConnection): int
    {
        return DB::connection($logConnection)->transaction(function () use ($trxConnection, $logConnection) {
            $row = DB::connection($logConnection)
                ->table('log_trx_sequence')
                ->where('shard_connection', $trxConnection)
                ->lockForUpdate()
                ->first();

            $nextSeq = ($row?->current_sequence ?? 0) + 1;

            DB::connection($logConnection)
                ->table('log_trx_sequence')
                ->updateOrInsert(
                    ['shard_connection' => $trxConnection],
                    ['current_sequence' => $nextSeq]
                );

            return $nextSeq;
        });
    }
}
```

### 4. TrxRecoveryCommand修正（シャーディング対応）

```php
// 復旧時はTrxDB接続名から自動的にLogDB接続を解決

public function handle(): int
{
    $trxShard = $this->argument('shard'); // 'trx1'
    $logShard = ShardMapper::getLogConnection($trxShard); // 'log1'
    
    // log1から変更ログを取得してtrx1を復旧
    $changes = DB::connection($logShard)
        ->table('log_trx_change')
        ->where('shard_connection', $trxShard)
        ->where('created_at', '>', $snapshotTime)
        ->where('created_at', '<=', $targetTime)
        ->orderBy('sequence_number', 'asc')
        ->get();
    
    // trx1へ変更を適用
    foreach ($changes as $change) {
        $this->applyChange($trxShard, $change);
    }
}
```

## Docker Compose設定

```yaml
# docker-compose.yml

services:
  # 既存のTrxDB
  db-trx1:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: nexus-local-trx1
      MYSQL_ROOT_PASSWORD: root
    volumes:
      - db-trx1-data:/var/lib/mysql
    ports:
      - "33071:3306"

  db-trx2:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: nexus-local-trx2
      MYSQL_ROOT_PASSWORD: root
    volumes:
      - db-trx2-data:/var/lib/mysql
    ports:
      - "33072:3306"

  # 新規: LogDBシャーディング
  db-log1:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: nexus-local-log1
      MYSQL_ROOT_PASSWORD: root
    volumes:
      - db-log1-data:/var/lib/mysql
    ports:
      - "33081:3306"

  db-log2:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: nexus-local-log2
      MYSQL_ROOT_PASSWORD: root
    volumes:
      - db-log2-data:/var/lib/mysql
    ports:
      - "33082:3306"

  # 既存: 共通LogDB（アクセスログ等）
  db-log:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: nexus-local-log
      MYSQL_ROOT_PASSWORD: root
    volumes:
      - db-log-data:/var/lib/mysql
    ports:
      - "63063:3306"

volumes:
  db-trx1-data:
  db-trx2-data:
  db-log1-data:
  db-log2-data:
  db-log-data:
```

## マイグレーション戦略（動的シャーディング対応）

### 1. 動的マイグレーションコマンド

```bash
# すべてのLogDBシャードに対してマイグレーションを実行（DB_TRX_SHARDSに応じて自動）
php artisan pitr:migrate

# ロールバック
php artisan pitr:rollback --step=1
```

### 2. カスタムArtisanコマンド

```php
// packages/nexus-pitr/src/Commands/PitrMigrateCommand.php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;

/**
 * PitrMigrateCommand
 * 
 * すべてのLogDBシャードに対してマイグレーションを実行
 * 動的シャーディング対応（DB_TRX_SHARDSに応じてlog1, log2, ...に実行）
 */
class PitrMigrateCommand extends Command
{
    protected $signature = 'pitr:migrate 
                            {--force : Force the operation to run when in production}
                            {--seed : Indicates if the seed task should be re-run}
                            {--step : Force the migrations to be run so they can be rolled back individually}';

    protected $description = 'Run PITR migrations on all LogDB shards dynamically';

    public function handle(): int
    {
        $logConnections = ShardMapper::getAllLogConnections();
        
        $this->info('Running PITR migrations on all LogDB shards...');
        $this->newLine();
        
        foreach ($logConnections as $logConnection) {
            $this->info("📦 Migrating LogDB: {$logConnection}");
            
            $options = [
                '--database' => $logConnection,
                '--path' => 'database/migrations/log',
            ];
            
            if ($this->option('force')) {
                $options['--force'] = true;
            }
            
            if ($this->option('seed')) {
                $options['--seed'] = true;
            }
            
            if ($this->option('step')) {
                $options['--step'] = true;
            }
            
            $exitCode = Artisan::call('migrate', $options, $this->getOutput());
            
            if ($exitCode !== 0) {
                $this->error("❌ Migration failed for {$logConnection}");
                return self::FAILURE;
            }
            
            $this->info("✅ Migration completed for {$logConnection}");
            $this->newLine();
        }
        
        $this->info('🎉 All PITR migrations completed successfully!');
        
        return self::SUCCESS;
    }
}
```

## 運用上の考慮事項

### 1. バックアップ戦略

```
単一LogDB:
  1つのバックアップジョブ

シャード化LogDB:
  各シャード独立でバックアップ（並列化可能）
  
例:
  0 2 * * * /backup/mysql-backup.sh log1
  0 2 * * * /backup/mysql-backup.sh log2
```

### 2. ディスク容量監視

```
各LogDBシャードのディスク使用量を独立監視:
  log1: 500GB
  log2: 500GB
  
アラート閾値: 80%使用時
```

### 3. パーティショニング

```
各LogDBシャードで月毎パーティション:

ALTER TABLE log_trx_change PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202608 VALUES LESS THAN (202609),
    PARTITION p202609 VALUES LESS THAN (202610),
    ...
);
```

## 段階的移行計画（既存システムがある場合）

### Phase 1: LogDBシャード追加（並行稼働）

1. db-log1, db-log2を新規作成
2. 新規ログを両方に二重書き込み（既存log + 新log1/log2）
3. 動作確認

### Phase 2: 完全移行

1. 新規ログは新log1/log2のみに書き込み
2. 既存logはread-onlyに
3. 旧ログデータのアーカイブ

### Phase 3: 旧LogDB削除

1. 保持期間経過後、既存logを削除

## まとめ

### 推奨: LogDB動的シャーディング対応

**YES、LogDBもTrxDBと同様に動的シャーディング対応すべき**

#### 理由

1. ✅ **スケーラビリティ**: 書き込み負荷分散
2. ✅ **復旧効率**: 対象シャードのみクエリで高速
3. ✅ **障害隔離**: 影響範囲を最小化
4. ✅ **将来拡張性**: 環境変数のみでシャード数を増減可能
5. ✅ **運用柔軟性**: コード変更不要でスケール可能

#### 実装コスト

- Docker Compose: コンテナ数は環境変数で制御
- コード変更: ShardMapper（動的生成対応）、PitrMigrateCommand（自動マイグレーション）
- マイグレーション: `php artisan pitr:migrate`で全シャード自動適用

#### ROI

**非常に高い**: 動的シャーディングにより運用コストを削減しながらスケーラビリティを確保

#### 導入済み（実装完了）

- ✅ config/database.php: 動的シャーディング対応（DB_TRX_SHARDS環境変数）
- ✅ ShardMapper: 動的マッピング対応
- ✅ PitrMigrateCommand: 動的マイグレーション対応
- ✅ ユニットテスト: 動的シャーディングテスト完備
