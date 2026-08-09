# LogDBシャーディング設計書

## 概要

LogDBをTrxDBと同様にシャーディング対応し、スケーラビリティと復旧効率を向上させます。

## シャーディング方針

### 基本原則

```
TrxDB : LogDB = 1 : 1 の対応関係
```

- **trx1** → **log1** (trx1の変更ログ専用)
- **trx2** → **log2** (trx2の変更ログ専用)

### 利点

#### 1. スケーラビリティ

```
単一LogDB:
  書き込み: 全シャード分が集中 → ボトルネック
  読み込み: 復旧時に全ログをスキャン → 遅い

シャード化LogDB:
  書き込み: シャード毎に分散 → 並列化可能
  読み込み: 対象シャードのみクエリ → 高速
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

### 物理構成

```
Docker Compose:
  db-log1  (MySQL 8.4) ← trx1のログ専用
  db-log2  (MySQL 8.4) ← trx2のログ専用
  
本番環境:
  RDS log1 (Multi-AZ) ← trx1のログ専用
  RDS log2 (Multi-AZ) ← trx2のログ専用
```

### Laravel接続設定

```php
// config/database.php

'connections' => [
    // 既存のTrxDB接続
    'trx1' => [...],
    'trx2' => [...],
    
    // LogDBシャーディング接続（新規追加）
    'log1' => [
        'driver' => 'mysql',
        'host' => env('DB_LOG1_HOST', 'db-log1'),
        'port' => env('DB_LOG1_PORT', '3306'),
        'database' => env('DB_LOG1_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-log1',
        'username' => env('DB_LOG1_USERNAME', 'root'),
        'password' => env('DB_LOG1_PASSWORD', 'root'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
    
    'log2' => [
        'driver' => 'mysql',
        'host' => env('DB_LOG2_HOST', 'db-log2'),
        'port' => env('DB_LOG2_PORT', '3306'),
        'database' => env('DB_LOG2_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-log2',
        'username' => env('DB_LOG2_USERNAME', 'root'),
        'password' => env('DB_LOG2_PASSWORD', 'root'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
    
    // 既存の'log'接続は後方互換性のため残す（log_access等の共通ログ用）
    'log' => [
        'driver' => 'mysql',
        'host' => env('DB_LOG_HOST', 'db-log'),
        'port' => env('DB_LOG_PORT', '3306'),
        'database' => env('DB_LOG_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-log',
        'username' => env('DB_LOG_USERNAME', 'root'),
        'password' => env('DB_LOG_PASSWORD', 'root'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
];
```

### .env設定例

```env
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

### 1. TrxDBとLogDBのマッピング

```php
// nexus-pitr/src/Logger/ShardMapper.php

namespace NexusPitr\Logger;

class ShardMapper
{
    /**
     * TrxDB接続名から対応するLogDB接続名を取得
     */
    public static function getLogConnection(string $trxConnection): string
    {
        return match ($trxConnection) {
            'trx1' => 'log1',
            'trx2' => 'log2',
            default => throw new \InvalidArgumentException("Unknown trx connection: {$trxConnection}")
        };
    }
    
    /**
     * LogDB接続名から対応するTrxDB接続名を取得
     */
    public static function getTrxConnection(string $logConnection): string
    {
        return match ($logConnection) {
            'log1' => 'trx1',
            'log2' => 'trx2',
            default => throw new \InvalidArgumentException("Unknown log connection: {$logConnection}")
        };
    }
    
    /**
     * すべてのLogDB接続名を取得
     */
    public static function getAllLogConnections(): array
    {
        return ['log1', 'log2'];
    }
    
    /**
     * すべてのTrxDB接続名を取得
     */
    public static function getAllTrxConnections(): array
    {
        return ['trx1', 'trx2'];
    }
}
```

### 2. TrxChangeLogger修正（シャーディング対応）

```php
// nexus-pitr/src/Logger/TrxChangeLogger.php

namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;
use NexusPitr\Dto\ChangeLogDto;

class TrxChangeLogger
{
    public function __construct(
        private readonly SequenceManager $sequenceManager
    ) {}

    /**
     * TrxDB変更をLogDBに記録（シャーディング対応）
     */
    public function log(ChangeLogDto $changeLogDto): void
    {
        $trxConnection = $changeLogDto->getShardConnection();
        $logConnection = ShardMapper::getLogConnection($trxConnection);
        
        $sequenceNumber = $this->sequenceManager->getNextSequence(
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
    public function getNextSequence(string $trxConnection, string $logConnection): int
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
      - "33063:3306"

volumes:
  db-trx1-data:
  db-trx2-data:
  db-log1-data:
  db-log2-data:
  db-log-data:
```

## マイグレーション戦略

### 1. 新規LogDBシャードのマイグレーション

```bash
# log1のマイグレーション実行
php artisan migrate --database=log1 --path=packages/nexus-pitr/database/migrations

# log2のマイグレーション実行
php artisan migrate --database=log2 --path=packages/nexus-pitr/database/migrations
```

### 2. マイグレーションファイル

```php
// packages/nexus-pitr/database/migrations/2026_08_08_000001_create_log_trx_change.php

public function up(): void
{
    $connections = ['log1', 'log2']; // シャーディング対象接続
    
    foreach ($connections as $connection) {
        Schema::connection($connection)->create('log_trx_change', function (Blueprint $table) {
            // テーブル定義（設計書通り）
        });
    }
}

public function down(): void
{
    $connections = ['log1', 'log2'];
    
    foreach ($connections as $connection) {
        Schema::connection($connection)->dropIfExists('log_trx_change');
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

### 推奨: LogDBシャーディング対応

**YES、LogDBもTrxDBと同様にシャーディング対応すべき**

#### 理由

1. ✅ **スケーラビリティ**: 書き込み負荷分散
2. ✅ **復旧効率**: 対象シャードのみクエリで高速
3. ✅ **障害隔離**: 影響範囲を最小化
4. ✅ **将来拡張性**: TrxDB増設時にLogDBも自然に増設可能

#### 実装コスト

- Docker Compose: コンテナ2つ追加のみ
- コード変更: ShardMapper追加、数クラス修正
- マイグレーション: 既存マイグレーションを複数接続に適用

#### ROI

**高い**: 実装コストに対してスケーラビリティと復旧効率の向上が大きい

次のステップ: この設計でLogDBシャーディング対応を実装しますか？
