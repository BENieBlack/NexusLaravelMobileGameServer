# TrxDB Point-In-Time Recovery (PITR) 設計書

## 概要

TrxDBが故障した際に、MySQLスナップショット（ポイントインタイムリカバリー）とLogDBの変更履歴を組み合わせて、データを完全復旧する仕組みを提供します。

## 設計原則

### 基本コンセプト

```
TrxDB復旧 = MySQLスナップショット（T時点） + LogDB差分（T+1 〜 現在）
```

### アーキテクチャ方針

1. **Package化**: `nexus-pitr`として汎用的な復旧機能を提供
2. **非侵襲的**: 既存のRepositoryに最小限の変更で統合可能
3. **検証可能**: Dry-runと整合性検証を必須サポート
4. **柔軟性**: プレイヤー単位・テーブル単位・時刻範囲指定が可能

## システム構成

### DB構成

```
┌─────────────┐
│   SysDB     │ プレイヤー基本情報、シャーディング情報
└─────────────┘

┌─────────────┐
│ TrxDB (trx1)│ トランザクションデータ（シャード1）
│ TrxDB (trx2)│ トランザクションデータ（シャード2）
└─────────────┘
      ↓ すべての変更を記録
┌─────────────┐
│   LogDB     │ 統合変更ログ（log_trx_change）
│             │ + 既存個別ログ（log_item, log_unit...）
└─────────────┘
```

### PITR処理フロー

```
[障害発生]
    ↓
[1. MySQLスナップショット復元]
    ↓
[2. LogDBから差分取得]
    ↓
[3. シーケンス順に変更適用]
    ↓
[4. 整合性検証]
    ↓
[復旧完了]
```

## DB設計

### 1. log_trx_change（統合変更ログテーブル）

TrxDB全テーブルの変更を統一形式で記録します。

```php
Schema::connection('log')->create('log_trx_change', function (Blueprint $table) {
    // 基本情報
    $table->id()->comment('ログID（シーケンシャル、復旧順序保証）');
    $table->string('unique_request_id', 36)->comment('リクエスト一意ID (UUID)');
    $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
    $table->string('shard_connection', 10)->comment('シャード接続名 (trx1, trx2)');
    
    // 変更対象と操作種別
    $table->string('table_name', 50)->comment('変更対象テーブル名');
    $table->enum('operation', ['INSERT', 'UPDATE', 'DELETE'])->comment('操作種別');
    
    // 変更データ（JSON形式）
    $table->json('before_data')->nullable()->comment('変更前データ（UPDATE/DELETEの場合）');
    $table->json('after_data')->nullable()->comment('変更後データ（INSERT/UPDATEの場合）');
    $table->json('primary_key')->comment('対象レコードのプライマリキー情報');
    
    // 復旧用メタデータ
    $table->unsignedBigInteger('sequence_number')->comment('シャード内シーケンス番号');
    $table->dateTime('system_at')->comment('システム日時');
    $table->string('api_endpoint', 100)->nullable()->comment('変更を引き起こしたAPIエンドポイント');
    $table->text('stack_trace')->nullable()->comment('呼び出し元スタックトレース（デバッグ用）');
    
    // タイムスタンプ
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    
    // インデックス
    $table->index(['shard_connection', 'sequence_number'], 'idx_shard_seq');
    $table->index(['sys_player_id', 'created_at'], 'idx_player_time');
    $table->index('unique_request_id', 'idx_request');
    $table->index(['table_name', 'created_at'], 'idx_table_time');
    $table->index('created_at', 'idx_created');
});
```

#### データ例

```json
{
  "id": 123456,
  "unique_request_id": "550e8400-e29b-41d4-a716-446655440000",
  "sys_player_id": 1001,
  "shard_connection": "trx1",
  "table_name": "trx_item",
  "operation": "UPDATE",
  "before_data": {
    "sys_player_id": 1001,
    "mst_item_id": "item_gold_coin",
    "amount": 1000,
    "free_amount": 800,
    "paid_amount": 200
  },
  "after_data": {
    "amount": 1500,
    "free_amount": 1300,
    "paid_amount": 200
  },
  "primary_key": {
    "sys_player_id": 1001,
    "mst_item_id": "item_gold_coin"
  },
  "sequence_number": 789012,
  "system_at": "2026-08-08 14:23:45",
  "api_endpoint": "/api/gacha/draw",
  "created_at": "2026-08-08 14:23:45"
}
```

### 2. log_trx_sequence（シーケンス番号管理テーブル）

シャード毎にシーケンス番号を管理し、厳密な順序を保証します。

```php
Schema::connection('log')->create('log_trx_sequence', function (Blueprint $table) {
    $table->string('shard_connection', 10)->primary()->comment('シャード接続名');
    $table->unsignedBigInteger('current_sequence')->default(0)->comment('現在のシーケンス番号');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
});
```

### 3. log_trx_checksum（定期整合性検証用）

日次でTrxDBの状態をスナップショットし、整合性検証に使用します。

```php
Schema::connection('log')->create('log_trx_checksum', function (Blueprint $table) {
    $table->id()->comment('チェックサムID');
    $table->string('shard_connection', 10)->comment('シャード接続名');
    $table->string('table_name', 50)->comment('対象テーブル名');
    $table->unsignedBigInteger('record_count')->comment('レコード数');
    $table->string('checksum', 64)->comment('SHA256チェックサム');
    $table->dateTime('snapshot_at')->comment('スナップショット日時');
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
    
    $table->index(['shard_connection', 'table_name', 'snapshot_at']);
});
```

## Package構成

### nexus-pitrパッケージ構造

```
packages/nexus-pitr/
├── composer.json
├── src/
│   ├── NexusPitrServiceProvider.php
│   ├── Logger/
│   │   ├── TrxChangeLogger.php          # 変更ログ記録
│   │   ├── SequenceManager.php          # シーケンス番号管理
│   │   └── Contracts/
│   │       └── Loggable.php             # ログ記録インターフェイス
│   ├── Recovery/
│   │   ├── TrxRecoveryService.php       # 復旧ロジック本体
│   │   ├── ChangeApplicator.php         # 変更適用
│   │   └── RecoveryVerifier.php         # 整合性検証
│   ├── Commands/
│   │   ├── TrxRecoveryCommand.php       # 復旧コマンド
│   │   ├── TrxChecksumCommand.php       # チェックサム作成
│   │   └── TrxVerifyCommand.php         # 整合性検証
│   ├── Traits/
│   │   └── LogsChanges.php              # Repository用Trait
│   └── Dto/
│       ├── ChangeLogDto.php             # 変更ログDTO
│       └── RecoveryOptionsDto.php       # 復旧オプションDTO
└── database/
    └── migrations/
        ├── 2026_08_08_000001_create_log_trx_change.php
        ├── 2026_08_08_000002_create_log_trx_sequence.php
        └── 2026_08_08_000003_create_log_trx_checksum.php
```

## 実装詳細

### 1. TrxChangeLogger（変更ログ記録）

```php
namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;
use NexusPitr\Dto\ChangeLogDto;

class TrxChangeLogger
{
    public function __construct(
        private readonly SequenceManager $sequenceManager
    ) {}

    /**
     * TrxDB変更をLogDBに記録
     */
    public function log(ChangeLogDto $changeLogDto): void
    {
        $sequenceNumber = $this->sequenceManager->issueNextSequence(
            $changeLogDto->getShardConnection()
        );

        DB::connection('log')->table('log_trx_change')->insert([
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
            'stack_trace' => $changeLogDto->getStackTrace(),
        ]);
    }

    /**
     * バッチログ記録（複数変更を一度に記録）
     */
    public function logBatch(array $changeLogDtos): void
    {
        if (empty($changeLogDtos)) {
            return;
        }

        $shardConnection = $changeLogDtos[0]->getShardConnection();
        $baseSequence = $this->sequenceManager->reserveSequences(
            $shardConnection,
            count($changeLogDtos)
        );

        $records = [];
        foreach ($changeLogDtos as $index => $dto) {
            $records[] = [
                'unique_request_id' => $dto->getUniqueRequestId(),
                'sys_player_id' => $dto->getSysPlayerId(),
                'shard_connection' => $dto->getShardConnection(),
                'table_name' => $dto->getTableName(),
                'operation' => $dto->getOperation(),
                'before_data' => $dto->getBeforeData() ? json_encode($dto->getBeforeData()) : null,
                'after_data' => $dto->getAfterData() ? json_encode($dto->getAfterData()) : null,
                'primary_key' => json_encode($dto->getPrimaryKey()),
                'sequence_number' => $baseSequence + $index,
                'system_at' => $dto->getSystemAt(),
                'api_endpoint' => $dto->getApiEndpoint(),
                'stack_trace' => $dto->getStackTrace(),
            ];
        }

        DB::connection('log')->table('log_trx_change')->insert($records);
    }
}
```

### 2. SequenceManager（シーケンス番号管理）

```php
namespace NexusPitr\Logger;

use Illuminate\Support\Facades\DB;

class SequenceManager
{
    /**
     * 次のシーケンス番号を取得（アトミック）
     */
    public function issueNextSequence(string $shardConnection): int
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
```

### 3. LogsChanges Trait（Repository統合用）

```php
namespace NexusPitr\Traits;

use NexusPitr\Logger\TrxChangeLogger;
use NexusPitr\Dto\ChangeLogDto;
use Illuminate\Support\Facades\App;

trait LogsChanges
{
    private ?TrxChangeLogger $trxChangeLogger = null;

    /**
     * TrxChangeLoggerインスタンスを取得
     */
    protected function getTrxChangeLogger(): TrxChangeLogger
    {
        if ($this->trxChangeLogger === null) {
            $this->trxChangeLogger = App::make(TrxChangeLogger::class);
        }
        return $this->trxChangeLogger;
    }

    /**
     * INSERT操作をログ記録
     */
    protected function logInsert(
        string $shardConnection,
        string $tableName,
        array $data,
        array $primaryKey,
        int $sysPlayerId
    ): void {
        $this->getTrxChangeLogger()->log(new ChangeLogDto(
            uniqueRequestId: request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid()->toString(),
            sysPlayerId: $sysPlayerId,
            shardConnection: $shardConnection,
            tableName: $tableName,
            operation: 'INSERT',
            beforeData: null,
            afterData: $data,
            primaryKey: $primaryKey,
            systemAt: now(),
            apiEndpoint: request()->path() ?? 'console',
            stackTrace: config('app.debug') ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        ));
    }

    /**
     * UPDATE操作をログ記録
     */
    protected function logUpdate(
        string $shardConnection,
        string $tableName,
        array $beforeData,
        array $afterData,
        array $primaryKey,
        int $sysPlayerId
    ): void {
        $this->getTrxChangeLogger()->log(new ChangeLogDto(
            uniqueRequestId: request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid()->toString(),
            sysPlayerId: $sysPlayerId,
            shardConnection: $shardConnection,
            tableName: $tableName,
            operation: 'UPDATE',
            beforeData: $beforeData,
            afterData: $afterData,
            primaryKey: $primaryKey,
            systemAt: now(),
            apiEndpoint: request()->path() ?? 'console',
            stackTrace: config('app.debug') ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        ));
    }

    /**
     * DELETE操作をログ記録
     */
    protected function logDelete(
        string $shardConnection,
        string $tableName,
        array $beforeData,
        array $primaryKey,
        int $sysPlayerId
    ): void {
        $this->getTrxChangeLogger()->log(new ChangeLogDto(
            uniqueRequestId: request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid()->toString(),
            sysPlayerId: $sysPlayerId,
            shardConnection: $shardConnection,
            tableName: $tableName,
            operation: 'DELETE',
            beforeData: $beforeData,
            afterData: null,
            primaryKey: $primaryKey,
            systemAt: now(),
            apiEndpoint: request()->path() ?? 'console',
            stackTrace: config('app.debug') ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : null
        ));
    }
}
```

### 4. Repository統合例

```php
namespace App\Repositories\Trx;

use NexusPitr\Traits\LogsChanges;
use Illuminate\Support\Facades\DB;

class TrxItemRepository
{
    use LogsChanges;

    /**
     * アイテム数量を更新（ログ記録あり）
     */
    public function updateAmount(
        int $sysPlayerId,
        string $mstItemId,
        int $newAmount,
        int $newFreeAmount,
        int $newPaidAmount
    ): void {
        $connection = $this->getConnectionForPlayer($sysPlayerId);

        // 変更前データ取得
        $beforeData = DB::connection($connection)
            ->table('trx_item')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        if (!$beforeData) {
            throw new \RuntimeException("Item not found: {$mstItemId}");
        }

        // 更新実行
        DB::connection($connection)
            ->table('trx_item')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->update([
                'amount' => $newAmount,
                'free_amount' => $newFreeAmount,
                'paid_amount' => $newPaidAmount,
            ]);

        // ログ記録
        $this->logUpdate(
            shardConnection: $connection,
            tableName: 'trx_item',
            beforeData: (array) $beforeData,
            afterData: [
                'amount' => $newAmount,
                'free_amount' => $newFreeAmount,
                'paid_amount' => $newPaidAmount,
            ],
            primaryKey: [
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
            ],
            sysPlayerId: $sysPlayerId
        );
    }

    private function getConnectionForPlayer(int $sysPlayerId): string
    {
        // シャーディングロジック
        return 'trx1'; // 簡略化
    }
}
```

### 5. TrxRecoveryCommand（復旧コマンド）

```php
namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use NexusPitr\Recovery\TrxRecoveryService;
use NexusPitr\Dto\RecoveryOptionsDto;

class TrxRecoveryCommand extends Command
{
    protected $signature = 'trx:recover 
        {shard : シャード名 (trx1, trx2)}
        {--snapshot-time= : スナップショット時刻 (Y-m-d H:i:s)}
        {--target-time= : 復旧目標時刻 (デフォルト=現在)}
        {--player-id= : 特定プレイヤーのみ復旧}
        {--table= : 特定テーブルのみ復旧}
        {--dry-run : 実際の復旧は行わず、適用するログのみ表示}
        {--verify : 復旧後にLogDBと整合性検証}
        {--batch-size=100 : バッチサイズ}';

    protected $description = 'LogDBからTrxDBをポイントインタイムリカバリー';

    public function __construct(
        private readonly TrxRecoveryService $recoveryService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $options = new RecoveryOptionsDto(
            shard: $this->argument('shard'),
            snapshotTime: $this->option('snapshot-time'),
            targetTime: $this->option('target-time') ?? now()->format('Y-m-d H:i:s'),
            playerId: $this->option('player-id') ? (int) $this->option('player-id') : null,
            tableName: $this->option('table'),
            dryRun: $this->option('dry-run'),
            verify: $this->option('verify'),
            batchSize: (int) $this->option('batch-size')
        );

        $this->displayHeader($options);

        if (!$this->confirm('復旧を実行しますか？', !$options->getDryRun())) {
            $this->info('キャンセルしました');
            return 0;
        }

        $result = $this->recoveryService->recover($options, $this->output);

        $this->displayResult($result);

        return $result['success'] ? 0 : 1;
    }

    private function displayHeader(RecoveryOptionsDto $options): void
    {
        $this->info('=== TrxDB Point-In-Time Recovery ===');
        $this->table(
            ['項目', '値'],
            [
                ['シャード', $options->getShard()],
                ['スナップショット時刻', $options->getSnapshotTime()],
                ['復旧目標時刻', $options->getTargetTime()],
                ['プレイヤーID', $options->getPlayerId() ?? '全プレイヤー'],
                ['対象テーブル', $options->getTableName() ?? '全テーブル'],
                ['モード', $options->getDryRun() ? 'Dry-run' : '本番実行'],
                ['整合性検証', $options->getVerify() ? '有効' : '無効'],
            ]
        );
    }

    private function displayResult(array $result): void
    {
        $this->newLine();
        $this->info('=== 復旧結果 ===');
        $this->table(
            ['項目', '値'],
            [
                ['適用ログ件数', $result['applied_count'] ?? 0],
                ['処理時間', ($result['elapsed_time'] ?? 0) . '秒'],
                ['ステータス', $result['success'] ? '成功' : '失敗'],
            ]
        );

        if (!empty($result['errors'])) {
            $this->error('エラーが発生しました:');
            foreach ($result['errors'] as $error) {
                $this->warn("  - {$error}");
            }
        }
    }
}
```

## 運用フロー

### 日常運用

#### 1. MySQLスナップショット自動取得

```bash
# cron設定例（1時間毎）
0 * * * * /usr/local/bin/mysql-backup.sh trx1 >> /var/log/mysql-backup.log 2>&1
0 * * * * /usr/local/bin/mysql-backup.sh trx2 >> /var/log/mysql-backup.log 2>&1
```

#### 2. チェックサム定期作成（整合性検証用）

```bash
# 日次でチェックサム作成
0 3 * * * php /var/www/html/artisan trx:checksum --all
```

#### 3. ログDBサイズ監視・アーカイブ

```bash
# 3ヶ月以上前のログをアーカイブ
0 4 1 * * php /var/www/html/artisan trx:archive-logs --older-than=90
```

### 障害発生時の復旧手順

#### Step 1: 状況確認

```bash
# 最新のスナップショット確認
ls -lh /backup/mysql/trx1/snapshots/

# LogDBの最終ログ確認
php artisan trx:verify trx1 --check-latest
```

#### Step 2: スナップショット復元

```bash
# MySQLスナップショットから復元（例: 12:00のスナップショット）
mysql-restore.sh trx1 /backup/mysql/trx1/snapshots/2026-08-08_12:00:00.sql
```

#### Step 3: Dry-run確認

```bash
# まずDry-runで適用内容を確認
php artisan trx:recover trx1 \
    --snapshot-time="2026-08-08 12:00:00" \
    --target-time="2026-08-08 14:35:22" \
    --dry-run
```

#### Step 4: 本番復旧実行

```bash
# 問題なければ本番適用
php artisan trx:recover trx1 \
    --snapshot-time="2026-08-08 12:00:00" \
    --target-time="2026-08-08 14:35:22" \
    --verify \
    --batch-size=500
```

#### Step 5: 整合性検証

```bash
# チェックサムで整合性確認
php artisan trx:verify trx1 \
    --compare-checksum \
    --timestamp="2026-08-08 14:35:22"
```

### 部分復旧（特定プレイヤーのみ）

```bash
# プレイヤーID 12345のデータのみ復旧
php artisan trx:recover trx1 \
    --snapshot-time="2026-08-08 12:00:00" \
    --player-id=12345 \
    --verify
```

### 特定テーブルのみ復旧

```bash
# trx_itemテーブルのみ復旧
php artisan trx:recover trx1 \
    --snapshot-time="2026-08-08 12:00:00" \
    --table=trx_item \
    --verify
```

## パフォーマンス最適化

### 1. バッチログ記録

複数変更を一度に記録してINSERTクエリ数を削減：

```php
// Before: 10回INSERT
for ($i = 0; $i < 10; $i++) {
    $this->logUpdate(...);
}

// After: 1回のバッチINSERT
$changeLogs = [];
for ($i = 0; $i < 10; $i++) {
    $changeLogs[] = new ChangeLogDto(...);
}
$this->getTrxChangeLogger()->logBatch($changeLogs);
```

### 2. 非同期ログ記録（オプション）

Redis Queueを使用して、ログ記録を非同期化（レスポンス速度優先の場合）：

```php
// config/nexus-pitr.php
'async_logging' => env('PITR_ASYNC_LOGGING', false),
'queue_connection' => env('PITR_QUEUE_CONNECTION', 'redis'),
```

### 3. LogDBパーティショニング

月毎にパーティション分割して、古いデータのクエリ速度を維持：

```sql
ALTER TABLE log_trx_change PARTITION BY RANGE (YEAR(created_at) * 100 + MONTH(created_at)) (
    PARTITION p202608 VALUES LESS THAN (202609),
    PARTITION p202609 VALUES LESS THAN (202610),
    ...
);
```

## セキュリティ考慮事項

### 1. アクセス制限

```php
// api/app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // 本番環境では復旧コマンドを管理者のみ実行可能に
    if (app()->environment('production')) {
        Gate::define('execute-pitr', function ($user) {
            return $user->hasRole('admin');
        });
    }
}
```

### 2. ログ改ざん検知（将来拡張）

```php
// log_trx_changeにHMAC署名フィールド追加
$table->string('hmac_signature', 64)->nullable()->comment('HMAC-SHA256署名');
```

### 3. 監査ログ

復旧コマンド実行履歴を記録：

```php
Schema::connection('log')->create('log_pitr_execution', function (Blueprint $table) {
    $table->id();
    $table->string('shard_connection', 10);
    $table->dateTime('snapshot_time');
    $table->dateTime('target_time');
    $table->unsignedInteger('applied_count');
    $table->string('executed_by', 50)->comment('実行者');
    $table->boolean('success');
    $table->text('error_message')->nullable();
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
});
```

## テスト戦略

### 1. ユニットテスト

```php
// tests/Unit/NexusPitr/SequenceManagerTest.php
public function test_get_next_sequence_is_incremental()
{
    $manager = new SequenceManager();
    
    $seq1 = $manager->issueNextSequence('trx1');
    $seq2 = $manager->issueNextSequence('trx1');
    
    $this->assertEquals($seq1 + 1, $seq2);
}
```

### 2. 統合テスト

```php
// tests/Feature/NexusPitr/RecoveryTest.php
public function test_recovery_restores_data_correctly()
{
    // テストデータ作成
    $player = $this->createTestPlayer();
    
    // スナップショット時点のデータ保存
    $snapshotTime = now();
    $snapshotData = $this->capturePlayerData($player->id);
    
    // データ変更
    $this->changePlayerItems($player->id);
    $targetTime = now();
    
    // TrxDBをスナップショット状態に戻す
    $this->restoreSnapshot($snapshotTime);
    
    // 復旧実行
    $result = $this->recoveryService->recover(new RecoveryOptionsDto(
        shard: 'trx1',
        snapshotTime: $snapshotTime->format('Y-m-d H:i:s'),
        targetTime: $targetTime->format('Y-m-d H:i:s'),
        playerId: $player->id
    ));
    
    // 復旧後のデータが目標時点と一致することを確認
    $recoveredData = $this->capturePlayerData($player->id);
    $this->assertEquals($targetData, $recoveredData);
}
```

## 制限事項と将来の拡張

### 現在の制限事項

1. **外部キー制約**: 復旧時に外部キー制約を考慮した順序制御が必要
2. **大規模データ**: 数百万件の変更ログ適用には時間がかかる可能性
3. **マルチテナント**: 現在はプレイヤー単位だが、ギルド等の共有データは別途考慮必要

### 将来の拡張案

1. **並列復旧**: プレイヤー毎に並列処理で復旧速度向上
2. **増分スナップショット**: LogDBベースの軽量スナップショット機能
3. **リアルタイムレプリケーション**: CDC（Change Data Capture）によるリアルタイム同期
4. **GUI管理画面**: 復旧状況の可視化とワンクリック復旧

## まとめ

本PITR設計により、TrxDB故障時に以下を実現：

- ✅ **データ完全性保証**: スナップショット + LogDBで秒単位の復旧
- ✅ **柔軟な復旧**: プレイヤー単位・テーブル単位・時刻範囲指定
- ✅ **検証可能性**: Dry-runとチェックサムによる整合性確認
- ✅ **非侵襲的統合**: 既存Repositoryへの最小限の変更
- ✅ **運用しやすさ**: Artisanコマンドによる簡単な操作

次のステップ: `nexus-pitr`パッケージの実装
