# Repositoryパターン実装ガイド

このドキュメントは、本プロジェクトにおけるRepositoryパターンの実装方法を説明します。

---

## 目次

1. [Repositoryパターンとは](#repositoryパターンとは)
2. [ディレクトリ構造](#ディレクトリ構造)
3. [基底クラスの使い方](#基底クラスの使い方)
4. [実装例](#実装例)
5. [キャッシュ戦略](#キャッシュ戦略)
6. [テストでの使用](#テストでの使用)

---

## Repositoryパターンとは

### 目的

- **データアクセスの抽象化**: ビジネスロジックをデータ取得の詳細から分離
- **キャッシュ管理の一元化**: 重複クエリの削減と高速化
- **テストの容易性**: モックやスタブへの置き換えが簡単
- **コードの再利用性**: 同じクエリを複数箇所で使いまわせる

### 使用するタイミング

以下の場合にRepositoryを作成することを推奨します：

- ✅ 同じクエリを複数のServiceで使用する場合
- ✅ 複雑なクエリやJOINを含む場合
- ✅ キャッシュを利用してパフォーマンスを改善したい場合
- ✅ データアクセスをテストしやすくしたい場合

以下の場合はRepositoryを作成せず、Modelの直接使用で十分です：

- ❌ 単純な `Model::find($id)` のような基本的なクエリ
- ❌ 1箇所でしか使われないクエリ

---

## ディレクトリ構造

```
api/app/Repositories/
├── _BaseInterface.php           # 基底インターフェース
├── CacheRecordTrait.php         # キャッシュレコードTrait
│
├── Sys/                         # システムDB用Repository
│   ├── _BaseSysRepository.php
│   ├── SysPlayerRepository.php
│   ├── SysPlayerDeviceRepository.php
│   ├── SysPlayerTokenRepository.php
│   ├── SysDeployRepository.php
│   └── SysMaintenanceRepository.php
│
├── Mst/                         # マスターDB用Repository
│   ├── _BaseMstRepository.php
│   ├── MstItemRepository.php
│   └── MstUnitRepository.php
│
└── Trx/                         # トランザクションDB用Repository
    ├── _BaseTrxRepository.php
    ├── TrxPlayerRepository.php
    ├── TrxItemRepository.php
    └── TrxUnitRepository.php
```

---

## 基底クラスの使い方

### 基底クラスの選択

各データベースごとに基底クラスが用意されています：

| データベース | 基底クラス | 説明 |
|------------|-----------|------|
| sys | `_BaseSysRepository` | システムDB用（プレイヤー、デプロイ管理等） |
| mst | `_BaseMstRepository` | マスターDB用（アイテム、キャラ、スキル等） |
| trx | `_BaseTrxRepository` | トランザクションDB用（プレイヤーデータ） |

### 基底クラスが提供する機能

#### 1. 基本的なCRUD操作

```php
// ID検索（キャッシュあり）
$player = $repository->selectById(123);

// 新規作成
$player = $repository->insert([
    'my_id' => 'player_123',
    'uuid' => 'abc-def-ghi',
]);

// 更新
$repository->update($player, ['name' => 'NewName']);

// 削除
$repository->delete($player);
```

#### 2. キャッシュ機能

```php
// キャッシュ付きクエリ実行
$player = $this->cacheRemember(
    "my_id:{$myId}",  // キャッシュキー
    fn() => $this->newQuery()->where('my_id', $myId)->first()
);

// キャッシュクリア
$this->forgetCache("my_id:{$myId}");

// 全キャッシュクリア
$this->flushCache();
```

#### 3. モデルインスタンス取得

```php
// 新しいモデルインスタンス
$model = $this->newModel();

// 新しいクエリビルダー
$query = $this->newQuery();
```

---

## 実装例

### 1. 基本的なRepository

```php
<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayer;

class SysPlayerRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayer::class;
    protected string $cachePrefix = 'sys:player';

    /**
     * my_idでプレイヤーを検索
     */
    public function selectByMyId(string $myId): ?SysPlayer
    {
        return $this->cacheRemember(
            "my_id:{$myId}",
            fn() => $this->newQuery()->where('my_id', $myId)->first()
        );
    }

    /**
     * uuidでプレイヤーを検索
     */
    public function selectByUuid(string $uuid): ?SysPlayer
    {
        return $this->cacheRemember(
            "uuid:{$uuid}",
            fn() => $this->newQuery()->where('uuid', $uuid)->first()
        );
    }

    /**
     * my_idの重複チェック
     */
    public function existsByMyId(string $myId): bool
    {
        return $this->cacheRemember(
            "exists:my_id:{$myId}",
            fn() => $this->newQuery()->where('my_id', $myId)->exists()
        );
    }
}
```

### 2. リレーション付きRepository

```php
<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysDeploy;

class SysDeployRepository extends _BaseSysRepository
{
    protected string $modelClass = SysDeploy::class;
    protected string $cachePrefix = 'sys:deploy';

    /**
     * 最新のダウンロード可能なデプロイを取得（リレーション付き）
     */
    public function selectLatestDownloadable(): ?SysDeploy
    {
        return $this->cacheRemember(
            'latest:downloadable',
            fn() => $this->newQuery()
                ->with(['deployMaster', 'deployAsset'])
                ->where('is_active', true)
                ->where('start_at', '<=', now())
                ->orderBy('deploy_key', 'desc')
                ->first()
        );
    }

    /**
     * deploy_keyで検索（リレーション付き）
     */
    public function selectByDeployKey(int $deployKey): ?SysDeploy
    {
        return $this->cacheRemember(
            "deploy_key:{$deployKey}",
            fn() => $this->newQuery()
                ->with(['deployMaster', 'deployAsset'])
                ->where('deploy_key', $deployKey)
                ->first()
        );
    }
}
```

### 3. 複数レコード取得Repository

```php
<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstItem;
use Illuminate\Support\Collection;

class MstItemRepository extends _BaseMstRepository
{
    protected string $modelClass = MstItem::class;
    protected string $cachePrefix = 'mst:item';

    /**
     * カテゴリ別にアイテムリストを取得
     */
    public function selectListByCategory(string $category): Collection
    {
        return $this->cacheRemember(
            "category:{$category}",
            fn() => $this->newQuery()
                ->where('category', $category)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * IDリストでアイテムを一括取得
     */
    public function selectListByIds(array $ids): Collection
    {
        // IDが多い場合はキャッシュしない
        if (count($ids) > 100) {
            return $this->newQuery()->whereIn('id', $ids)->get();
        }

        $cacheKey = 'ids:' . md5(implode(',', $ids));
        return $this->cacheRemember(
            $cacheKey,
            fn() => $this->newQuery()->whereIn('id', $ids)->get()
        );
    }
}
```

---

## キャッシュ戦略

### キャッシュキーの命名規則

キャッシュキーは以下の形式で命名します：

```
{cachePrefix}:{検索条件}:{値}
```

**例:**
- `sys:player:my_id:player_123`
- `sys:player:uuid:abc-def-ghi`
- `sys:deploy:latest:downloadable`
- `mst:item:category:weapon`

### キャッシュTTL（有効期限）

| データの種類 | TTL | 理由 |
|------------|-----|------|
| システムデータ（sys） | 1時間 | 頻繁に参照されるが、更新は少ない |
| マスターデータ（mst） | 24時間 | ほぼ変更されない静的データ |
| トランザクションデータ（trx） | 5分 | 頻繁に更新されるプレイヤーデータ |

TTLは各基底クラスの `$cacheTtl` プロパティで設定できます。

### キャッシュドライバーの自動切り替え

- **本番環境（production）**: Redis
- **テスト環境（testing）**: Array

これにより、テスト実行時にRedisが不要になります。

### キャッシュ無効化のタイミング

データを更新した際は、関連するキャッシュを明示的にクリアします：

```php
// 例: プレイヤー名を更新した場合
public function updatePlayerName(SysPlayer $player, string $newName): void
{
    $this->update($player, ['name' => $newName]);
    
    // キャッシュクリア
    $this->forgetCache("my_id:{$player->my_id}");
    $this->forgetCache("uuid:{$player->uuid}");
}
```

---

## テストでの使用

### モックの作成

```php
use App\Repositories\Sys\SysPlayerRepository;
use Mockery;

public function test_example(): void
{
    // Repositoryをモック
    $mockRepository = Mockery::mock(SysPlayerRepository::class);
    
    // 期待する動作を定義
    $mockRepository->shouldReceive('selectByMyId')
        ->once()
        ->with('player_123')
        ->andReturn($this->createMockPlayer());
    
    // DIコンテナに登録
    $this->app->instance(SysPlayerRepository::class, $mockRepository);
    
    // テスト実行
    // ...
}
```

### 統合テストでの使用

```php
use App\Repositories\Sys\SysPlayerRepository;

public function test_integration_example(): void
{
    // 実際のRepositoryを使用
    $repository = app(SysPlayerRepository::class);
    
    // テストデータ作成
    $player = $repository->insert([
        'my_id' => 'test_player',
        'uuid' => 'test-uuid',
    ]);
    
    // 検索テスト
    $found = $repository->selectByMyId('test_player');
    $this->assertNotNull($found);
    $this->assertEquals('test_player', $found->my_id);
    
    // キャッシュからの取得テスト（2回目）
    $cached = $repository->selectByMyId('test_player');
    $this->assertEquals($found->id, $cached->id);
}
```

---

## ベストプラクティス

### ✅ Good

```php
// 1. キャッシュキーは一意性を保つ
$this->cacheRemember("my_id:{$myId}", ...);

// 2. 複雑なクエリはメソッドに切り出す
public function selectActivePlayersByLevel(int $level): Collection
{
    return $this->cacheRemember(
        "active:level:{$level}",
        fn() => $this->newQuery()
            ->where('is_active', true)
            ->where('level', '>=', $level)
            ->get()
    );
}

// 3. Serviceからの呼び出し
class PlayerService
{
    public function __construct(
        private readonly SysPlayerRepository $playerRepository
    ) {}

    public function getPlayer(string $myId): ?SysPlayer
    {
        return $this->playerRepository->selectByMyId($myId);
    }
}
```

### ❌ Bad

```php
// 1. キャッシュキーが曖昧
$this->cacheRemember("player", ...); // どのプレイヤー？

// 2. Repositoryにビジネスロジックを書く
public function selectAndProcessPlayer(string $myId): array
{
    $player = $this->selectByMyId($myId);
    // ← ビジネスロジックはServiceで実装すべき
    return [
        'player' => $player,
        'level' => $player->level * 2,
    ];
}

// 3. Modelを直接使うべき単純なクエリ
public function selectById(int $id): ?SysPlayer
{
    // ← 基底クラスにあるので不要
}
```

---

## まとめ

1. **データベースごとに基底クラスを継承**してRepositoryを作成
2. **キャッシュ機能を積極的に活用**してパフォーマンスを改善
3. **キャッシュキーは一意性を保つ**ように命名
4. **ビジネスロジックはServiceに実装**し、Repositoryはデータアクセスに専念
5. **テスト環境では自動的にArrayキャッシュ**が使用される

これらのルールに従うことで、保守性が高く、パフォーマンスに優れたコードを実現できます。
