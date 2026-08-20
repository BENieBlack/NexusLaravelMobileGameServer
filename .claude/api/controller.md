# Controller 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、Controllerクラスの実装ルールを定義します。

---

## 目次

- [基本原則](#基本原則)
- [実装ルール](#実装ルール)
- [実装すべきもの・してはいけないもの](#実装すべきものしてはいけないもの)
- [実装例](#実装例)
- [チェックリスト](#チェックリスト)

---

## 基本原則

### Controllerは薄く保つ

Controllerはプレゼンテーション層として、HTTPリクエストとレスポンスの橋渡しのみを行います。

```php
// ✅ Good: Controllerは薄く、UseCaseに処理を委譲
class AuthController extends Controller
{
    public function version(
        VersionCheckRequest $request,
        CheckUseCase $useCase
    ): JsonResponse {
        $response = $useCase->handle($request);
        return $response->toJsonResponse();
    }
}

// ❌ Bad: Controllerにビジネスロジックを書かない
class AuthController extends Controller
{
    public function version(Request $request): JsonResponse
    {
        // バリデーション処理
        // データ取得処理
        // ビジネスロジック処理
        // レスポンス生成処理
        // ← 全部Controllerに書くのはNG
    }
}
```

---

## 実装ルール

### 1. コードの行数制限

**Controllerのメソッドは10行以内を目安にする**

理由：
- 責務の肥大化を防ぐ
- 可読性の向上
- テストの容易さ

### 2. 処理フロー

**Request → UseCase → Response の流れのみ**

```php
public function action(
    CustomRequest $request,
    CustomUseCase $useCase
): JsonResponse {
    // 1. FormRequestから入力を受け取る
    // 2. UseCaseに処理を委譲
    // 3. Responseを返却
    $response = $useCase->handle($request);
    return $response->toJsonResponse();
}
```

### 3. 依存性注入

**コンストラクタインジェクションまたはメソッドインジェクションを使用**

```php
// ✅ Good: メソッドインジェクション
class AuthController extends Controller
{
    public function version(
        VersionCheckRequest $request,
        CheckUseCase $useCase
    ): JsonResponse {
        $response = $useCase->handle($request);
        return $response->toJsonResponse();
    }
}

// ✅ Good: コンストラクタインジェクション（複数メソッドで共有する場合）
class PlayerController extends Controller
{
    public function __construct(
        private readonly PlayerRepository $playerRepository
    ) {}
    
    public function show(int $id): JsonResponse
    {
        // ...
    }
    
    public function update(int $id, PlayerUpdateRequest $request): JsonResponse
    {
        // ...
    }
}
```

### 4. 認証・認可

**認証・認可はMiddlewareで行う**

```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/player/profile', [PlayerController::class, 'profile']);
});

// Controller内では認証済みを前提
class PlayerController extends Controller
{
    public function profile(): JsonResponse
    {
        $player = Auth::user(); // 認証済みユーザーを取得
        // ...
    }
}
```

---

## 実装すべきもの・してはいけないもの

### ✅ 実装すべき

1. **FormRequestの受け取り**
   ```php
   public function store(CreateItemRequest $request): JsonResponse
   ```

2. **UseCaseの呼び出し**
   ```php
   $response = $useCase->handle($request);
   ```

3. **Responseの返却**
   ```php
   return $response->toJsonResponse();
   ```

### ❌ 実装してはいけない

1. **ビジネスロジック** → UseCaseまたはServiceへ
   ```php
   // ❌ Bad
   public function calculate(Request $request): JsonResponse
   {
       $result = $request->value1 + $request->value2; // ビジネスロジック
       return response()->json(['result' => $result]);
   }
   ```

2. **バリデーション** → FormRequestへ
   ```php
   // ❌ Bad
   public function store(Request $request): JsonResponse
   {
       $validated = $request->validate([
           'name' => 'required|string',
       ]); // バリデーションはFormRequestで行う
   }
   ```

3. **データベースアクセス** → ServiceまたはRepositoryへ
   ```php
   // ❌ Bad
   public function show(int $id): JsonResponse
   {
       $player = Player::find($id); // 直接DBアクセス
       return response()->json($player);
   }
   ```

---

## 実装例

### 基本的なCRUD

```php
namespace App\Http\Controllers;

use App\Http\Requests\Player\CreatePlayerRequest;
use App\Http\Requests\Player\UpdatePlayerRequest;
use App\UseCases\Player\CreatePlayerUseCase;
use App\UseCases\Player\UpdatePlayerUseCase;
use App\UseCases\Player\DeletePlayerUseCase;
use Illuminate\Http\JsonResponse;

class PlayerController extends Controller
{
    /**
     * プレイヤー作成
     */
    public function store(
        CreatePlayerRequest $request,
        CreatePlayerUseCase $useCase
    ): JsonResponse {
        $response = $useCase->handle($request);
        return $response->toJsonResponse();
    }
    
    /**
     * プレイヤー更新
     */
    public function update(
        int $id,
        UpdatePlayerRequest $request,
        UpdatePlayerUseCase $useCase
    ): JsonResponse {
        $response = $useCase->handle($id, $request);
        return $response->toJsonResponse();
    }
    
    /**
     * プレイヤー削除
     */
    public function destroy(
        int $id,
        DeletePlayerUseCase $useCase
    ): JsonResponse {
        $response = $useCase->handle($id);
        return $response->toJsonResponse();
    }
}
```

### 認証が必要なエンドポイント

```php
namespace App\Http\Controllers;

use App\Http\Requests\Auth\VersionCheckRequest;
use App\UseCases\Auth\CheckUseCase;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * バージョンチェック（認証不要）
     */
    public function version(
        VersionCheckRequest $request,
        CheckUseCase $useCase
    ): JsonResponse {
        $response = $useCase->handle($request);
        return $response->toJsonResponse();
    }
}
```

### エラーハンドリング

```php
namespace App\Http\Controllers;

use App\Http\Requests\Gacha\DrawGachaRequest;
use App\UseCases\Gacha\DrawGachaUseCase;
use App\Exceptions\InsufficientCurrencyException;
use Illuminate\Http\JsonResponse;

class GachaController extends Controller
{
    /**
     * ガチャ実行
     */
    public function draw(
        DrawGachaRequest $request,
        DrawGachaUseCase $useCase
    ): JsonResponse {
        try {
            $response = $useCase->handle($request);
            return $response->toJsonResponse();
        } catch (InsufficientCurrencyException $e) {
            return response()->json([
                'error' => 'Insufficient currency',
                'message' => $e->getMessage(),
                'code' => 'INSUFFICIENT_CURRENCY',
            ], 400);
        }
    }
}
```

**注意**: エラーハンドリングは通常、`App\Exceptions\Handler`で集中管理することを推奨します。Controller内での個別処理は最小限に。

---

## チェックリスト

Controller実装時に以下を確認してください：

### 設計

- [ ] メソッドは10行以内に収まっている
- [ ] ビジネスロジックを含んでいない
- [ ] データベースに直接アクセスしていない
- [ ] バリデーションをController内で行っていない

### 依存性

- [ ] FormRequestを使用している
- [ ] UseCaseに処理を委譲している
- [ ] Responseクラスを返している
- [ ] 依存性注入を使用している（new演算子を使っていない）

### 認証・認可

- [ ] 認証・認可はMiddlewareで行っている
- [ ] Controller内で認証チェックを行っていない

### 命名

- [ ] コントローラー名は`{リソース名}Controller`
- [ ] メソッド名はRESTfulな命名（index, show, store, update, destroy等）
- [ ] または、アクションを明確に表す動詞（signIn, signUp, draw等）

### レスポンス

- [ ] 必ず`JsonResponse`を返している
- [ ] Responseクラスの`toJsonResponse()`を使用している
- [ ] HTTPステータスコードが適切

---

## 関連ドキュメント

- [Request実装ルール](./request.md) - FormRequestの実装方法
- [Response実装ルール](./response.md) - Responseクラスの実装方法
- [UseCaseパターン](../coding-standards.md#2-usecaseの実装ルール) - UseCaseの実装方法
- [API設計](../api.md#api設計) - ルーティングとレスポンス形式

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
