# ApiSession 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、ApiSession（APIリクエストコンテキスト管理）の実装ルールを定義します。

---

## 基本原則

**ApiSessionはAPIリクエストのコンテキスト情報（プレイヤーID等）を管理するユーティリティクラスです。**

### 設計思想

1. **唯一のプレイヤーID管理方法**: Repository内で`$sysPlayerId`フィールドを持たず、ApiSessionから動的に取得
2. **Middleware統合**: 認証時に自動的にApiSessionにプレイヤーIDを設定
3. **パフォーマンス最適化**: Repository内でキャッシュ機構を実装し、2回目以降はキャッシュを使用
4. **依存性注入の徹底**: Service/UseCase層で静的メソッド呼び出しを排除し、DIパターンを使用

---

## ApiSessionの配置

```
api/app/Utilities/ApiSession.php
```

**配置理由:**
- ClockUtility等と同じユーティリティクラスとして配置
- Servicesディレクトリではなく、Utilitiesディレクトリに配置

---

## ApiSessionの実装

### 基本構造

```php
namespace App\Utilities;

/**
 * APIセッション管理クラス
 * 
 * APIリクエストのコンテキスト情報（プレイヤーID等）を管理します。
 */
class ApiSession
{
    private static ?int $sysPlayerId = null;

    /**
     * プレイヤーIDを設定
     * 
     * @param int $sysPlayerId sys_player.id
     * @return void
     */
    public static function setSysPlayerId(int $sysPlayerId): void
    {
        self::$sysPlayerId = $sysPlayerId;
    }

    /**
     * プレイヤーIDを取得
     * 
     * @return int sys_player.id
     * @throws \RuntimeException プレイヤーIDが未設定の場合
     */
    public static function getSysPlayerId(): int
    {
        if (self::$sysPlayerId === null) {
            throw new \RuntimeException('sys_player_id is not set in ApiSession');
        }

        return self::$sysPlayerId;
    }

    /**
     * プレイヤーIDが設定されているか確認
     * 
     * @return bool
     */
    public static function hasSysPlayerId(): bool
    {
        return self::$sysPlayerId !== null;
    }

    /**
     * セッションをクリア（主にテスト用）
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::$sysPlayerId = null;
    }
}
```

---

## Middlewareでの設定

### VerifyAccessToken Middleware

認証時にApiSessionにプレイヤーIDを自動設定します。

```php
namespace App\Http\Middleware;

use App\Utilities\ApiSession;
use Closure;
use Illuminate\Http\Request;

class VerifyAccessToken
{
    public function handle(Request $request, Closure $next)
    {
        // アクセストークンの検証...
        $token = $this->verifyAccessToken($accessToken);
        
        // ApiSessionにプレイヤーIDを設定
        ApiSession::setSysPlayerId($token->sys_player_id);
        
        return $next($request);
    }
}
```

---

## Repositoryでの使用

### _BaseTrxRepository

トランザクションデータ用のRepository基底クラス。

```php
namespace App\Repositories\Trx;

use App\Utilities\ApiSession;

abstract class _BaseTrxRepository extends _BaseRepository
{
    /**
     * キャッシュされたプレイヤーID
     * @var int|null
     */
    protected ?int $cachedSysPlayerId = null;

    /**
     * プレイヤーIDを取得（キャッシュ付き）
     * 
     * @return int sys_player.id
     */
    protected function getSysPlayerId(): int
    {
        if ($this->cachedSysPlayerId === null) {
            $this->cachedSysPlayerId = ApiSession::getSysPlayerId();
        }

        return $this->cachedSysPlayerId;
    }

    /**
     * プレイヤーIDでモデルを取得
     * 
     * @param int $id
     * @return mixed
     */
    public function findById(int $id)
    {
        $sysPlayerId = $this->getSysPlayerId();
        
        return $this->newQuery()
            ->where('sys_player_id', $sysPlayerId)
            ->where('id', $id)
            ->first();
    }
}
```

### _BaseLogRepository

ログデータ用のRepository基底クラス。

```php
namespace App\Repositories\Log;

use App\Utilities\ApiSession;

abstract class _BaseLogRepository extends _BaseRepository
{
    /**
     * キャッシュされたプレイヤーID
     * @var int|null
     */
    protected ?int $cachedSysPlayerId = null;

    /**
     * プレイヤーIDを取得（キャッシュ付き）
     * 
     * @return int sys_player.id
     */
    protected function getSysPlayerId(): int
    {
        if ($this->cachedSysPlayerId === null) {
            $this->cachedSysPlayerId = ApiSession::getSysPlayerId();
        }

        return $this->cachedSysPlayerId;
    }
}
```

---

## Service層での依存性注入

### ❌ Bad: 静的メソッド呼び出し

```php
namespace App\Domain\Wallet\Services;

use App\Utilities\ApiSession;

class WalletService
{
    public function addCurrency(string $mstItemId, int $amount): array
    {
        // ❌ 静的メソッド呼び出し（テスト困難）
        $sysPlayerId = ApiSession::getSysPlayerId();
        
        // ...
    }
}
```

### ✅ Good: 依存性注入

```php
namespace App\Domain\Wallet\Services;

use App\Utilities\ApiSession;

class WalletService
{
    /**
     * コンストラクタインジェクション
     */
    public function __construct(
        private readonly ApiSession $apiSession,
        private readonly WalletRepository $walletRepository,
    ) {}

    public function addCurrency(string $mstItemId, int $amount): array
    {
        // ✅ DIパターン（テスト容易）
        $sysPlayerId = $this->apiSession->getSysPlayerId();
        
        // ...
    }
}
```

**重要: 現在の実装では、ApiSessionは静的メソッドのみを持つため、DIする場合はインスタンスメソッドに変更する必要があります。ただし、既存のアーキテクチャでは静的メソッド呼び出しがRepository内で使用されているため、Service層でのDIは将来的な改善として検討してください。**

---

## UseCase層での使用

### テストでの使用例

```php
namespace Tests\Feature;

use App\Utilities\ApiSession;
use Tests\TestCase;

class UnitLevelUpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // テスト用プレイヤーIDを設定
        ApiSession::setSysPlayerId(1);
    }

    protected function tearDown(): void
    {
        // テスト後にクリア
        ApiSession::clear();
        
        parent::tearDown();
    }

    public function test_unit_level_up(): void
    {
        // ApiSessionが自動的にプレイヤーIDを提供
        $response = $this->postJson('/api/unit/level-up', [
            'trx_unit_id' => 123,
            'mst_item_id' => 'item_unit_exp',
            'use_count' => 1,
        ]);

        $response->assertStatus(200);
    }
}
```

---

## 設計の利点

### 1. クリーンなアーキテクチャ

**✅ Before（旧実装）:**
- Repositoryコンストラクタに`$sysPlayerId`を渡す必要があった
- 全てのメソッドで`$sysPlayerId`を引数に追加する必要があった

**✅ After（現在の実装）:**
- RepositoryはApiSessionから自動的にプレイヤーIDを取得
- メソッドシグネチャがシンプルになる

### 2. パフォーマンス最適化

```php
// 初回呼び出し: ApiSessionから取得
$sysPlayerId = $this->getSysPlayerId();  // ApiSession::getSysPlayerId()

// 2回目以降: キャッシュから取得
$sysPlayerId = $this->getSysPlayerId();  // $this->cachedSysPlayerId
```

### 3. テスタビリティ

```php
// テストで簡単にプレイヤーIDを設定可能
ApiSession::setSysPlayerId(999);

// Repository/Service/UseCaseが自動的に999を使用
$repo = new TrxItemRepository();
$item = $repo->findById(123);  // sys_player_id=999 で検索
```

---

## 実装例

### Repository

```php
// ✅ Good: ApiSessionから自動取得
class TrxUnitRepository extends _BaseTrxRepository
{
    public function findById(int $trxUnitId): ?TrxUnit
    {
        $sysPlayerId = $this->getSysPlayerId();  // ApiSessionから取得（キャッシュ付き）
        
        return $this->newQuery()
            ->where('sys_player_id', $sysPlayerId)
            ->where('id', $trxUnitId)
            ->first();
    }
}
```

### Service

```php
// ✅ Good: Repositoryが自動的にApiSessionを使用
class UnitLevelService
{
    public function __construct(
        private readonly TrxUnitRepository $trxUnitRepository,
    ) {}

    public function addExp(int $trxUnitId, int $exp): array
    {
        // Repositoryが内部でApiSessionを使用
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        
        // ...
    }
}
```

### UseCase

```php
// ✅ Good: すべてのレイヤーでApiSessionが自動的に機能
class UnitLevelUpUseCase
{
    public function __construct(
        private readonly UnitLevelService $unitLevelService,
    ) {}

    public function handle(int $trxUnitId, string $mstItemId, int $useCount): array
    {
        // ServiceとRepositoryが内部でApiSessionを使用
        return $this->unitLevelService->addExp($trxUnitId, 100);
    }
}
```

---

## チェックリスト

### Repository実装時
- [ ] `$sysPlayerId`フィールドを持たない
- [ ] `getSysPlayerId()`で初回のみApiSessionから取得
- [ ] `$cachedSysPlayerId`でキャッシュを実装
- [ ] コンストラクタに`$sysPlayerId`引数を持たない

### Service実装時
- [ ] Repositoryが自動的にApiSessionを使用することを理解
- [ ] プレイヤーIDを明示的に渡す必要がない

### UseCase実装時
- [ ] トランザクション内で自動的にApiSessionが機能することを確認
- [ ] テスト時に`ApiSession::setSysPlayerId()`を使用

### Middleware実装時
- [ ] 認証時に`ApiSession::setSysPlayerId()`を呼び出す
- [ ] リクエスト処理後に必要に応じて`ApiSession::clear()`を呼び出す

---

## まとめ

**ApiSessionは、プレイヤーIDを一元管理し、Repository/Service/UseCaseのコードをクリーンに保つための重要なコンポーネントです。**

- **Middleware**: 認証時にApiSessionを設定
- **Repository**: ApiSessionから自動的にプレイヤーIDを取得（キャッシュ付き）
- **Service/UseCase**: プレイヤーIDを意識する必要がない

このパターンにより、コードの可読性、保守性、テスタビリティが大幅に向上します。

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
