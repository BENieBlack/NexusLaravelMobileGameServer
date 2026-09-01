# トランザクション管理ルール / Transaction Management Rules

このドキュメントでは、sysデータベースとtrx/logデータベースのトランザクション管理の実装ルールを定義します。

## 目次

- [sysデータベースのトランザクション管理](#sysデータベースのトランザクション管理)
- [SysQueryManagerの実装](#sysquerymanagerの実装)
- [トランザクション実行フロー](#トランザクション実行フロー)
- [実装例](#実装例)

---

## sysデータベースのトランザクション管理

### 設計方針

**重要: sysデータベースは基本的にバッチINSERTを使用し、一部のテーブルのみ個別INSERTでIDを取得する。**

#### 個別INSERT（insertGetId()）が必要なテーブル

以下の3つのテーブルのみ、自動インクリメントIDをビジネスロジックで即座に使用するため、個別INSERTを実行します:

- `sys_player` - プレイヤーマスター（`sys_player_id`が必要）
- `sys_player_device` - デバイス情報（`sys_player_device_id`が必要）
- `sys_player_token` - トークン情報（将来的にIDを使用する可能性があるため）

**理由:**
- `sys_player.id`は、trxテーブルやlogテーブルで外部キーとして使用される
- `sys_player_device.id`も同様に、`sys_player_token`テーブルで使用される
- 自動インクリメントIDを取得するには、`insertGetId()`を使用する必要がある
- トランザクション内でINSERTすることで、ロールバック時の整合性が保たれる

#### バッチINSERTが可能なテーブル

上記3つ以外のsysテーブルは、バッチINSERT（`DB::table()->insert()`）で一括実行可能

---

## QueryManagerの実装（統合版）

### 概要

`QueryManager`は、3つのデータベース（Sys/Trx/Log）を統合管理するQueryManagerで、各データベースに応じた最適なINSERT方式を使用します。

**各データベースのINSERT方式:**

| データベース | INSERT方式 | ID取得 | 対象テーブル |
|------------|----------|--------|-----------|
| **Sys** | 個別INSERT（`insertGetId()`） | ✅ 取得してモデルに自動設定 | sys_player, sys_player_device, sys_player_token |
| **Sys** | バッチINSERT（`DB::table()->insert()`） | ❌ 取得不可 | 上記3つ以外のsysテーブル |
| **Trx** | バッチINSERT（`DB::table()->insert()`） | ❌ 取得不可 | 全てのtrxテーブル |
| **Log** | バッチINSERT（`DB::table()->insert()`） | ❌ 取得不可 | 全てのlogテーブル |

### 主要メソッド

**QueryManager.php:**

```php
namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class QueryManager
{
    private array $repositories = [];

    /**
     * Repositoryを登録
     */
    public function registerRepository($repository): void
    {
        $className = get_class($repository);
        if (!isset($this->repositories[$className])) {
            $this->repositories[$className] = $repository;
        }
    }

    /**
     * 課金ログのみを実行（トランザクション外）
     */
    public function execPurchaseQuery(): void
    {
        // 課金ログを抽出して実行...
    }

    /**
     * Sysのみを実行（部分実行）
     * PlayerServiceで段階的にプレイヤーとデバイスを作成してIDを取得するために使用
     */
    public function execSysQuery(): void
    {
        $sysRepositories = array_filter($this->repositories, function ($repository) {
            return $repository instanceof \App\Repositories\Sys\_BaseSysRepository;
        });

        foreach ($sysRepositories as $repository) {
            $this->execSysInserts($repository);
            $this->execSysUpdates($repository);
        }
    }

    /**
     * 全Repositoryのキューイングされたクエリを実行
     */
    public function execAllQuery(): void
    {
        // Sys/Trx/Logの全てのクエリを実行...
    }

    /**
     * Sys用のINSERT実行
     * sys_player, sys_player_device, sys_player_tokenのみ個別INSERT
     */
    private function execSysInserts($repository): void
    {
        $insertModels = $repository->getQueuedInsertModels();
        if (empty($insertModels)) {
            return;
        }

        $connection = DB::connection('sys');
        $tableName = $insertModels[0]->getTable();

        // sys_player, sys_player_device, sys_player_tokenのみ個別INSERT
        if (in_array($tableName, ['sys_player', 'sys_player_device', 'sys_player_token'])) {
            foreach ($insertModels as $model) {
                $id = $connection->table($tableName)->insertGetId($model->getAttributes());
                $model->setAttribute($model->getKeyName(), $id);
            }
        } else {
            // その他はバッチINSERT
            $insertData = array_map(fn($model) => $model->getAttributes(), $insertModels);
            $connection->table($tableName)->insert($insertData);
        }
    }
}
```

**重要なポイント:**
1. **統合管理** - Sys/Trx/Logを1つのQueryManagerで管理
2. **execSysQuery()** - Sysのみを部分的に実行（PlayerServiceで使用）
3. **3つのテーブルのみ個別INSERT** - sys_player, sys_player_device, sys_player_token
4. **シングルトン登録必須** - `app()->make()`で同じインスタンスを取得するため

---

## トランザクション実行フロー

### UseCaseTraitの実装

```php
trait UseCaseTrait
{
    protected function executeWithTransaction(callable $callback, ?int $sysPlayerId = null)
    {
        // 1. QueryManagerをシングルトンとして取得
        $queryManager = app()->make(QueryManager::class);
        
        // 2. トランザクション開始（sys, trx, log）
        DB::connection('sys')->beginTransaction();
        DB::connection('trx')->beginTransaction();
        DB::connection('log')->beginTransaction();

        try {
            // 3. コールバック実行（ビジネスロジック + キューイング）
            $result = $callback();

            // 4. クリーンアップ処理（is_delete=trueレコードの削除）
            if ($sysPlayerId !== null) {
                $cleanupService = app(PlayerCleanupService::class);
                $cleanupService->cleanup($sysPlayerId);
            }

            // 5. キューイングされたクエリを一括実行
            $queryManager->execPurchaseQuery();  // 課金ログを先に実行
            $queryManager->execAllQuery();       // Sys/Trx/Logを実行

            // 6. コミット
            DB::connection('sys')->commit();
            DB::connection('trx')->commit();
            DB::connection('log')->commit();

            return $result;
        } catch (\Exception $e) {
            // 7. ロールバック
            DB::connection('sys')->rollBack();
            DB::connection('trx')->rollBack();
            DB::connection('log')->rollBack();
            throw $e;
        }
    }
}
```

### 実行フロー図

```
1. トランザクション開始（sys, trx, log）
   ↓
2. コールバック実行（SELECT + キューイング）
   ├── sys_playerをキューイング
   ├── sys_player_deviceをキューイング
   ├── sys_player_tokenをキューイング
   ├── trx_playerをキューイング
   └── log_signupをキューイング
   ↓
3. クリーンアップ処理（is_delete=true削除）
   ↓
4. キューイングされたクエリを一括実行
   ├── QueryManager::execPurchaseQuery() ← 課金ログを先に実行
   └── QueryManager::execAllQuery()
       ├── Sys: INSERT sys_player → ID取得 → モデルに設定
       ├── Sys: INSERT sys_player_device → ID取得 → モデルに設定
       ├── Sys: INSERT sys_player_token → ID取得 → モデルに設定
       ├── Sys: その他のテーブルはバッチINSERT
       ├── Trx: バッチINSERT trx_player
       └── Log: バッチINSERT log_signup
   ↓
5. コミット（sys, trx, log）
```

**重要:**
- トランザクション開始は`$callback()`実行**後**
- sys_player, sys_player_device, sys_player_tokenのみ個別INSERTでIDを取得
- 課金ログを先に実行してから、Sys/Trx/通常Logを実行
- エラー発生時は全てロールバックされる

---

## 実装例

### PlayerServiceでの使用例

```php
namespace App\Domain\Auth\Services;

class PlayerService
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly SysPlayerDeviceRepository $sysPlayerDeviceRepository,
        private readonly SysPlayerTokenRepository $sysPlayerTokenRepository,
    ) {}

    public function createPlayer(
        string $loginType,
        ?string $appleUserId,
        ?string $googleUserId,
        string $deviceUuid,
        string $platform,
        string $appVersion
    ): array {
        // 1. SysPlayerを作成（キューイング）
        $sysPlayer = new SysPlayer([
            'login_type' => $loginType,
            'apple_user_id' => $appleUserId,
            'google_user_id' => $googleUserId,
            'my_id' => Str::random(20),
            'uuid' => Str::uuid()->toString(),
            'name' => Str::random(20),
        ]);
        $this->sysPlayerRepository->setModel($sysPlayer);

        // ✅ 即座にexecSysQuery()を呼び出してIDを取得
        app()->make(QueryManager::class)->execSysQuery();

        // 2. SysPlayerDeviceを作成（キューイング）
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayer->id,  // ✅ 取得したIDを使用
            'device_uuid' => $deviceUuid,
            'platform' => $platform,
            'app_version' => $appVersion,
        ]);
        $this->sysPlayerDeviceRepository->setModel($sysPlayerDevice);

        // ✅ 即座にexecSysQuery()を呼び出してIDを取得
        app()->make(QueryManager::class)->execSysQuery();

        // 3. SysPlayerTokenを作成（キューイング）
        $refreshToken = Str::random(64);
        $sysPlayerToken = new SysPlayerToken([
            'sys_player_id' => $sysPlayer->id,
            'sys_player_device_id' => $sysPlayerDevice->id,  // ✅ 取得したIDを使用
            'refresh_token_hash' => hash('sha256', $refreshToken),
            'expires_at' => ClockUtility::now()->addDays(30),
        ]);
        $this->sysPlayerTokenRepository->setModel($sysPlayerToken);
        // ← sys_player_tokenは遅延実行（UseCaseTrait内でexecAllQuery()される）

        return [
            'sys_player_id' => $sysPlayer->id,
            'refresh_token' => $refreshToken,
        ];
    }
}
```

**ポイント:**
1. `sys_player`作成後、即座に`execSysQuery()`を呼び出し
2. 取得した`sys_player.id`を使って`sys_player_device`を作成
3. `sys_player_device`作成後、即座に`execSysQuery()`を呼び出し
4. 取得した`sys_player_device.id`を使って`sys_player_token`を作成
5. `sys_player_token`は`execSysQuery()`で個別INSERTされるが、IDは使用しない

---

## AppServiceProviderでのシングルトン登録

**重要: QueryManagerは必ずシングルトンとして登録する**

```php
namespace App\Providers;

use App\Repositories\QueryManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ QueryManagerをシングルトン登録
        $this->app->singleton(QueryManager::class);
    }
}
```

**理由:**
- `app()->make(QueryManager::class)`を呼び出すたびに新しいインスタンスが作成されると、レポジトリが登録されたインスタンスと異なるインスタンスで`execAllQuery()`や`execSysQuery()`を呼び出してしまう
- シングルトン登録により、全ての箇所で同じインスタンスが使用される

---

## _BaseSysRepositoryの実装

```php
namespace App\Repositories\Sys;

abstract class _BaseSysRepository extends _BaseRepository
{
    /**
     * モデルを設定し、QueryManagerに自動登録
     */
    protected function setModel($model): void
    {
        // 1. モデルをキューに追加
        $this->modelQueue[] = $model;

        // 2. QueryManagerに自身を登録（初回のみ）
        if (!$this->registeredToManager) {
            $queryManager = app()->make(QueryManager::class);
            $queryManager->registerRepository($this);
            $this->registeredToManager = true;
        }
    }

    /**
     * INSERT対象のモデルを取得
     */
    public function getQueuedInsertModels(): array
    {
        $insertModels = [];
        foreach ($this->modelQueue as $model) {
            if (!$model->exists) {
                $insertModels[] = $model;
            }
        }
        return $insertModels;
    }

    /**
     * UPDATE対象のモデルを取得
     */
    public function getQueuedUpdateModels(): array
    {
        $updateModels = [];
        foreach ($this->modelQueue as $model) {
            if ($model->exists && $model->isDirty()) {
                $updateModels[] = $model;
            }
        }
        return $updateModels;
    }

    /**
     * キューをクリア
     */
    public function clearQueue(): void
    {
        $this->modelQueue = [];
        $this->registeredToManager = false;
    }
}
```

---

## まとめ

### sysデータベースのトランザクション管理ルール

1. **QueryManagerを使用** - Sys/Trx/Logを統合管理
2. **3つのテーブルのみ個別INSERT** - sys_player, sys_player_device, sys_player_token
3. **IDを即座にモデルに設定** - `$model->setAttribute($model->getKeyName(), $id)`
4. **execSysQuery()で部分実行** - PlayerServiceで段階的にIDを取得
5. **シングルトン登録必須** - `AppServiceProvider`で`singleton()`登録
6. **トランザクション開始は最初** - `$callback()`実行後にトランザクション開始
7. **ロールバック可能** - エラー時はsys含めて全てロールバック

### チェックリスト

**QueryManager:**
- [ ] Sys/Trx/Logを統合管理
- [ ] sys_player, sys_player_device, sys_player_tokenのみ`insertGetId()`
- [ ] `execSysQuery()`メソッドを実装
- [ ] `execPurchaseQuery()`を先に実行
- [ ] `AppServiceProvider`でシングルトン登録

**_BaseSysRepository:**
- [ ] `setModel()`で`QueryManager`に自動登録
- [ ] `getQueuedInsertModels()`と`getQueuedUpdateModels()`を実装
- [ ] `clearQueue()`を実装

**PlayerService:**
- [ ] `sys_player`作成後、即座に`execSysQuery()`
- [ ] `sys_player_device`作成後、即座に`execSysQuery()`
- [ ] `sys_player_token`は`execSysQuery()`で個別INSERT

**UseCaseTrait:**
- [ ] トランザクション開始を`$callback()`実行**後**に配置
- [ ] `sys`接続もトランザクション管理に含める
- [ ] `execPurchaseQuery()` → `execAllQuery()`の順序で実行
- [ ] ロールバック時に`sys`もロールバック

---

## 関連ドキュメント

- [データベース設計](../database.md) - データベース全体の設計方針
- [アーキテクチャ設計](../architecture.md) - レイヤー構造とトランザクション管理
- [カスタムHTTPステータスコード](../api/custom-http-status.md) - 600番台エラーコード体系
