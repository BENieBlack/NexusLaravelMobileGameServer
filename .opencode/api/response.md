# Response 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、Responseクラスの実装ルールを定義します。

---

## 目次

- [基本原則](#基本原則)
- [実装ルール](#実装ルール)
- [実装例](#実装例)
- [チェックリスト](#チェックリスト)

---

## 基本原則

### Responseクラスで型安全なレスポンスを提供

Responseクラスは、APIレスポンスの構造を型安全に定義します。

```php
// ✅ Good: Responseクラスで型安全
class VersionResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly int $latestDeployId,
        public readonly int $latestDeployKey,
        public readonly ?array $master = null,
    ) {}
}

// ❌ Bad: 配列で返す（型安全でない）
return response()->json([
    'needs_update' => $needsUpdate,
    'latest_deploy_id' => $latestDeployId,
]); // タイポや型ミスに気づきにくい
```

---

## 実装ルール

### 1. _BaseResponseを継承

**すべてのResponseクラスは`_BaseResponse`を継承**

```php
namespace App\Http\Responses\Auth;

use App\Http\Responses\_BaseResponse;

class VersionResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly int $latestDeployId,
    ) {}
}
```

### 2. プロパティはreadonlyで定義

**イミュータブルなデータ構造を保証**

```php
public readonly bool $needsUpdate;
public readonly int $latestDeployId;
```

**理由**:
- レスポンスデータは不変であるべき
- 意図しない変更を防ぐ
- 並列処理でも安全

### 3. コンストラクタで初期化

**コンストラクタプロモーションを活用**

```php
public function __construct(
    public readonly bool $needsUpdate,
    public readonly int $latestDeployId,
    public readonly ?array $master = null,
) {}
```

### 4. モデルレスポンスは`toResponseArray()`を使用

**Eloquentモデルをレスポンスに含める場合は、モデルの`toResponseArray()`メソッドを使用**

```php
// ✅ Good: toResponseArray()を使用
class LevelUpResponse implements Responsable
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,
        public readonly TrxItem $trxItem,
    ) {}
    
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'trx_equipment' => $this->trxEquipment->toResponseArray(),
            'trx_item' => $this->trxItem->toResponseArray(),
        ]);
    }
}

// ❌ Bad: 手動でフィールドをマッピング
public function toResponse($request): JsonResponse
{
    return response()->json([
        'trx_equipment' => [
            'id' => $this->trxEquipment->id,
            'sys_player_id' => $this->trxEquipment->sys_player_id,
            // ... フィールドを手動でマッピング（保守性が悪い）
        ],
    ]);
}
```

**理由**:
- モデルに変換ロジックを集約（DRY原則）
- フィールド追加時にResponse側の修正不要
- 日付フィールドが自動的にISO8601形式に変換される
- 全モデルで統一されたレスポンス形式
- `sys_player_id`などの内部IDは自動的に除外される

**`_BaseModel::toResponseArray()`の実装**:

```php
abstract class _BaseModel extends Model
{
    /**
     * APIレスポンス用の配列に変換
     * 
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        $array = $this->toArray();
        
        // 日付フィールドをISO8601形式に変換
        foreach ($this->getDates() as $dateField) {
            if (isset($array[$dateField]) && $this->{$dateField} instanceof \DateTimeInterface) {
                $array[$dateField] = $this->{$dateField}->toIso8601String();
            }
        }
        
        // sys_player_idはユーザーに渡さない内部IDなので除外
        unset($array['sys_player_id']);
        
        return $array;
    }
}
```

### 5. レスポンスキーはテーブル名を使用

**モデルをレスポンスに含める場合、JSONキーはテーブル名を使用**

```php
// ✅ Good: テーブル名をキーに使用
return response()->json([
    'trx_equipment' => $this->trxEquipment->toResponseArray(),
    'trx_item' => $this->trxItem->toResponseArray(),
]);

// ❌ Bad: 短縮形や別名を使用
return response()->json([
    'equipment' => $this->trxEquipment->toResponseArray(),  // テーブル名と異なる
    'item' => $this->trxItem->toResponseArray(),            // テーブル名と異なる
]);
```

**理由**:
- クライアント側でデータ構造が明確
- データベーススキーマとの一貫性
- APIドキュメントが読みやすい

### 6. toJsonResponse()メソッド

**_BaseResponseが提供する`toJsonResponse()`を使用**

```php
// Controllerで使用
$response = new VersionResponse(
    needsUpdate: true,
    latestDeployId: 10,
);
return $response->toJsonResponse();
```

**_BaseResponseの実装例**:

```php
abstract class _BaseResponse
{
    public function toJsonResponse(): JsonResponse
    {
        return response()->json($this->toArray());
    }
    
    public function toArray(): array
    {
        $array = [];
        $reflection = new ReflectionClass($this);
        
        foreach ($reflection->getProperties() as $property) {
            $name = $this->toSnakeCase($property->getName());
            $value = $property->getValue($this);
            
            if ($value !== null) {
                $array[$name] = $value;
            }
        }
        
        return $array;
    }
    
    private function toSnakeCase(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
```

### 7. キャメルケースからスネークケースへの変換

**プロパティ名（キャメルケース）がJSON（スネークケース）に自動変換**

```php
class VersionResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,       // JSON: needs_update
        public readonly int $latestDeployId,     // JSON: latest_deploy_id
        public readonly int $latestDeployKey,    // JSON: latest_deploy_key
    ) {}
}
```

---

## 実装例

### シンプルなレスポンス

```php
namespace App\Http\Responses\Auth;

use App\Http\Responses\_BaseResponse;

class VersionResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly int $latestDeployId,
        public readonly int $latestDeployKey,
    ) {}
}

// 使用例
$response = new VersionResponse(
    needsUpdate: false,
    latestDeployId: 10,
    latestDeployKey: 100,
);
return $response->toJsonResponse();

// JSON出力
// {
//   "needs_update": false,
//   "latest_deploy_id": 10,
//   "latest_deploy_key": 100
// }
```

### モデルを含むレスポンス

```php
namespace App\Http\Responses\Equipment;

use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxItem;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class LevelUpResponse implements Responsable
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,
        public readonly TrxItem $trxItem,
    ) {}
    
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'trx_equipment' => $this->trxEquipment->toResponseArray(),
            'trx_item' => $this->trxItem->toResponseArray(),
        ]);
    }
}

// 使用例
$response = new LevelUpResponse(
    trxEquipment: $trxEquipment,  // TrxEquipmentモデルインスタンス
    trxItem: $trxItem,            // TrxItemモデルインスタンス
);
return $response;

// JSON出力
// {
//   "trx_equipment": {
//     "id": 123,
//     "mst_equipment_id": "sword_001",
//     "grade": 1,
//     "level": 15,
//     "level_exp": 1500,
//     "is_delete": 0,
//     "created_at": "2026-02-24T10:00:00+00:00",
//     "updated_at": "2026-02-24T10:30:00+00:00"
//   },
//   "trx_item": {
//     "mst_item_id": "exp_potion_001",
//     "amount": 45,
//     "is_delete": 0,
//     "created_at": "2026-02-20T08:00:00+00:00",
//     "updated_at": "2026-02-24T10:30:00+00:00"
//   }
// }
// 注: sys_player_idは除外されている
```

### ネストした構造

```php
namespace App\Http\Responses\Auth;

use App\Http\Responses\_BaseResponse;

class VersionResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly int $latestDeployId,
        public readonly int $latestDeployKey,
        public readonly ?array $master = null,
        public readonly ?array $asset = null,
    ) {}
}

// 使用例
$response = new VersionResponse(
    needsUpdate: true,
    latestDeployId: 10,
    latestDeployKey: 100,
    master: [
        'deploy_master_id' => 15,
        'hash' => 'abc123...',
    ],
    asset: [
        'deploy_asset_id' => 8,
        'hash' => 'def456...',
    ],
);
return $response->toJsonResponse();

// JSON出力
// {
//   "needs_update": true,
//   "latest_deploy_id": 10,
//   "latest_deploy_key": 100,
//   "master": {
//     "deploy_master_id": 15,
//     "hash": "abc123..."
//   },
//   "asset": {
//     "deploy_asset_id": 8,
//     "hash": "def456..."
//   }
// }
```

### コレクションレスポンス

```php
namespace App\Http\Responses\Player;

use App\Http\Responses\_BaseResponse;

class PlayerListResponse extends _BaseResponse
{
    /**
     * @param array<int, array{id: int, name: string, level: int}> $players
     */
    public function __construct(
        public readonly array $players,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}

// 使用例
$response = new PlayerListResponse(
    players: [
        ['id' => 1, 'name' => 'Player 1', 'level' => 50],
        ['id' => 2, 'name' => 'Player 2', 'level' => 35],
    ],
    total: 100,
    page: 1,
    perPage: 10,
);
return $response->toJsonResponse();

// JSON出力
// {
//   "players": [
//     {"id": 1, "name": "Player 1", "level": 50},
//     {"id": 2, "name": "Player 2", "level": 35}
//   ],
//   "total": 100,
//   "page": 1,
//   "per_page": 10
// }
```

### オプショナルなフィールド

```php
namespace App\Http\Responses\Player;

use App\Http\Responses\_BaseResponse;

class PlayerDetailResponse extends _BaseResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $level,
        public readonly ?string $avatarUrl = null,    // オプショナル
        public readonly ?array $equipment = null,      // オプショナル
    ) {}
}

// nullフィールドはJSON出力に含まれない
$response = new PlayerDetailResponse(
    id: 1,
    name: 'Player 1',
    level: 50,
    // avatarUrl, equipmentは省略
);

// JSON出力
// {
//   "id": 1,
//   "name": "Player 1",
//   "level": 50
// }
```

### エラーレスポンス

```php
namespace App\Http\Responses\Error;

use App\Http\Responses\_BaseResponse;
use Illuminate\Http\JsonResponse;

class ErrorResponse extends _BaseResponse
{
    public function __construct(
        public readonly string $error,
        public readonly string $message,
        public readonly string $code,
        public readonly int $statusCode = 400,
        public readonly ?array $errors = null,
    ) {}
    
    public function toJsonResponse(): JsonResponse
    {
        return response()->json($this->toArray(), $this->statusCode);
    }
}

// 使用例
$response = new ErrorResponse(
    error: 'Validation failed',
    message: 'The given data was invalid',
    code: 'VALIDATION_ERROR',
    statusCode: 422,
    errors: [
        'email' => ['The email field is required.'],
    ],
);
return $response->toJsonResponse();

// JSON出力（HTTPステータス: 422）
// {
//   "error": "Validation failed",
//   "message": "The given data was invalid",
//   "code": "VALIDATION_ERROR",
//   "errors": {
//     "email": ["The email field is required."]
//   }
// }
```

---

## チェックリスト

Response実装時に以下を確認してください：

### 設計

- [ ] `_BaseResponse`を継承している（または`Responsable`を実装）
- [ ] プロパティは`public readonly`で定義
- [ ] コンストラクタプロモーションを使用
- [ ] null許容フィールドは`?型 = null`で定義

### モデルレスポンス

- [ ] Eloquentモデルは`toResponseArray()`メソッドを使用
- [ ] JSONキーはテーブル名を使用（例: `trx_equipment`, `trx_item`）
- [ ] 手動でフィールドマッピングしていない

### 命名

- [ ] クラス名は`{リソース名}Response`
- [ ] プロパティ名はキャメルケース
- [ ] JSONではスネークケースに自動変換される

### 型安全性

- [ ] すべてのプロパティに型を指定
- [ ] 配列は`@param`でドキュメント化
- [ ] オプショナルなフィールドは`?型`

### 使用方法

- [ ] `toJsonResponse()`または`toResponse()`でJsonResponseを返す
- [ ] Controllerで直接使用
- [ ] エラーレスポンスはHTTPステータスコードを指定

---

## 関連ドキュメント

- [Controller実装ルール](./controller.md) - Controllerでの使用方法
- [API設計](../api.md#レスポンス形式) - レスポンス形式の詳細
- [コーディング規約](../coding-standards.md#5-responseの実装ルール) - Responseの実装ルール詳細

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
