# トランザクション管理ルール / Transaction Management Rules

このドキュメントでは、sysデータベースとtrx/logデータベースのトランザクション管理の実装ルールを定義します。

## 目次

- [sysデータベースのトランザクション管理](#sysデータベースのトランザクション管理)
- [QuerySysManagerの実装](#querysysmanagerの実装)
- [トランザクション実行フロー](#トランザクション実行フロー)
- [実装例](#実装例)

---

## sysデータベースのトランザクション管理

### 設計方針

**重要: sysデータベースは、即座にINSERTしてIDを取得する必要があるテーブルと、遅延実行可能なテーブルが混在する。**

#### 即座にINSERT実行が必要なテーブル

以下のテーブルは、自動インクリメントIDをビジネスロジックで即座に使用するため、トランザクション開始**前**にINSERTを実行する必要があります:

- `sys_player` - プレイヤーマスター（`sys_player_id`が必要）
- `sys_player_device` - デバイス情報（`sys_player_device_id`が必要）

**理由:**
- `sys_player.id`は、trxテーブルやlogテーブルで外部キーとして使用される
- `sys_player_device.id`も同様に、`sys_player_token`テーブルで使用される
- 自動インクリメントIDを取得するには、実際にINSERTを実行する必要がある
- トランザクション開始前にINSERTすることで、ロールバック時の整合性も保たれる

#### 遅延実行可能なテーブル

以下のテーブルは、トランザクション開始**後**に一括実行可能:

- `sys_player_token` - トークン情報（IDを即座に使う必要がない）
- その他のsysテーブル

---

## QuerySysManagerの実装

### 概要

`QuerySysManager`は、sysデータベース専用のQueryManagerで、**個別INSERT（`insertGetId()`）とID自動設定**を行います。

**QueryTrxManager/QueryLogManagerとの違い:**

| 機能 | QueryTrxManager | QuerySysManager |
|-----|----------------|-----------------|
| INSERT方式 | バッチINSERT（`DB::table()->insert()`） | 個別INSERT（`insertGetId()`） |
| ID取得 | ❌ 取得不可 | ✅ 取得してモデルに自動設定 |
| 実行タイミング | トランザクション開始後 | トランザクション開始前・後の両方 |
| 用途 | IDを即座に使わないテーブル | IDを即座に使うテーブル |

### 実装: QuerySysManager.php

```php
namespace App\Utilities;

use Illuminate\Support\Facades\DB;

class QuerySysManager
{
    /**
     * 登録されたRepositoryの配列
     * @var array<\App\Repositories\Sys\_BaseSysRepository>
     */
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
     * 全Repositoryのキューイングされたクエリを実行
     */
    public function execAllQuery(): void
    {
        foreach ($this->repositories as $repository) {
            $connection = DB::connection('sys');

            // 1. INSERT実行（個別INSERT）
            $insertModels = $repository->getQueuedInsertModels();
            foreach ($insertModels as $model) {
                $attributes = $model->getAttributes();
                
                // insertGetId()でIDを取得
                $id = $connection->table($model->getTable())->insertGetId($attributes);
                
                // ✅ モデルにIDを自動設定
                $model->setAttribute($model->getKeyName(), $id);
            }

            // 2. UPDATE実行
            $updateModels = $repository->getQueuedUpdateModels();
            foreach ($updateModels as $model) {
                $connection->table($model->getTable())
                    ->where($model->getKeyName(), $model->getKey())
                    ->update($model->getDirty());
            }
        }
    }

    /**
     * 全Repositoryのキューをクリア
     */
    public function clearAllQueues(): void
    {
        foreach ($this->repositories as $repository) {
            $repository->clearQueue();
        }
    }
}
```

**重要なポイント:**
1. **`insertGetId()`を使用** - 個別INSERTでIDを取得
2. **`$model->setAttribute()`でIDを設定** - Eloquentモデルに取得したIDを設定
3. **シングルトン登録必須** - `app()->make()`で同じインスタンスを取得するため

---

## トランザクション実行フロー

### UseCaseTraitの実装

```php
trait UseCaseTrait
{
    protected function executeWithTransaction(callable $callback, ?int $sysPlayerId = null)
    {
        // 1. QuerySysManagerをシングルトンとして取得
        $querySysManager = app()->make(QuerySysManager::class);
        
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
            $querySysManager->execAllQuery();  // ✅ sys専用
            app(QueryTrxManager::class)->execAllQuery();
            app(QueryLogManager::class)->execAllQuery();

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
   ├── QuerySysManager::execAllQuery()
   │   ├── INSERT sys_player → ID取得 → モデルに設定
   │   ├── INSERT sys_player_device → ID取得 → モデルに設定
   │   └── INSERT sys_player_token
   ├── QueryTrxManager::execAllQuery()
   │   └── INSERT trx_player
   └── QueryLogManager::execAllQuery()
       └── INSERT log_signup
   ↓
5. コミット（sys, trx, log）
```

**重要:**
- トランザクション開始は`$callback()`実行**前**
- これにより、sysのINSERTもロールバック可能になる
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
            'name' => Str::random(20),  // デフォルトはmy_idと同じ
        ]);
        $this->sysPlayerRepository->setModel($sysPlayer);

        // ✅ 即座にexecAllQuery()を呼び出してIDを取得
        app()->make(QuerySysManager::class)->execAllQuery();

        // 2. SysPlayerDeviceを作成（キューイング）
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayer->id,  // ✅ 取得したIDを使用
            'device_uuid' => $deviceUuid,
            'platform' => $platform,
            'app_version' => $appVersion,
        ]);
        $this->sysPlayerDeviceRepository->setModel($sysPlayerDevice);

        // ✅ 即座にexecAllQuery()を呼び出してIDを取得
        app()->make(QuerySysManager::class)->execAllQuery();

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
1. `sys_player`作成後、即座に`execAllQuery()`を呼び出し
2. 取得した`sys_player.id`を使って`sys_player_device`を作成
3. `sys_player_device`作成後、即座に`execAllQuery()`を呼び出し
4. 取得した`sys_player_device.id`を使って`sys_player_token`を作成
5. `sys_player_token`は遅延実行（UseCaseTraitでまとめて実行される）

---

## AppServiceProviderでのシングルトン登録

**重要: QuerySysManagerは必ずシングルトンとして登録する**

```php
namespace App\Providers;

use App\Utilities\QuerySysManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ QuerySysManagerをシングルトン登録
        $this->app->singleton(QuerySysManager::class);
    }
}
```

**理由:**
- `app()->make(QuerySysManager::class)`を呼び出すたびに新しいインスタンスが作成されると、レポジトリが登録されたインスタンスと異なるインスタンスで`execAllQuery()`を呼び出してしまう
- シングルトン登録により、全ての箇所で同じインスタンスが使用される

---

## _BaseSysRepositoryの実装

```php
namespace App\Repositories\Sys;

abstract class _BaseSysRepository extends _BaseRepository
{
    /**
     * モデルを設定し、QuerySysManagerに自動登録
     */
    protected function setModel($model): void
    {
        // 1. 元の状態を保存
        $this->originalStateArray[$model->getKey()] = $model->getOriginalForRepository();

        // 2. モデルをキューに追加
        $this->modelQueue[] = $model;

        // 3. QuerySysManagerに自身を登録（初回のみ）
        if (!$this->registeredToManager) {
            $queryManager = app()->make(QuerySysManager::class);
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
        $this->originalStateArray = [];
        $this->registeredToManager = false;
    }
}
```

---

## まとめ

### sysデータベースのトランザクション管理ルール

1. **QuerySysManagerを使用** - sysデータベース専用のQueryManager
2. **個別INSERT（`insertGetId()`）** - 自動インクリメントIDを取得
3. **IDを即座にモデルに設定** - `$model->setAttribute($model->getKeyName(), $id)`
4. **シングルトン登録必須** - `AppServiceProvider`で`singleton()`登録
5. **即座実行と遅延実行の使い分け** - IDが必要なテーブルは即座に`execAllQuery()`
6. **トランザクション開始は最初** - `$callback()`実行前にトランザクション開始
7. **ロールバック可能** - エラー時はsys含めて全てロールバック

### チェックリスト

**QuerySysManager:**
- [ ] `insertGetId()`で個別INSERTを実装
- [ ] 取得したIDを`$model->setAttribute()`で設定
- [ ] `AppServiceProvider`でシングルトン登録

**_BaseSysRepository:**
- [ ] `setModel()`で`QuerySysManager`に自動登録
- [ ] `getQueuedInsertModels()`と`getQueuedUpdateModels()`を実装
- [ ] `clearQueue()`を実装

**PlayerService（またはsysテーブルを使うService）:**
- [ ] `sys_player`作成後、即座に`execAllQuery()`
- [ ] `sys_player_device`作成後、即座に`execAllQuery()`
- [ ] `sys_player_token`は遅延実行（UseCaseTraitに任せる）

**UseCaseTrait:**
- [ ] トランザクション開始を`$callback()`実行**前**に移動
- [ ] `sys`接続もトランザクション管理に含める
- [ ] `QuerySysManager::execAllQuery()`を追加
- [ ] ロールバック時に`sys`もロールバック

---

## 関連ドキュメント

- [データベース設計](../database.md) - データベース全体の設計方針
- [アーキテクチャ設計](../architecture.md) - レイヤー構造とトランザクション管理
- [カスタムHTTPステータスコード](./custom-http-status.md) - 600番台エラーコード体系
