# DTO 設計ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、DTO（Data Transfer Object）の設計ルールを定義します。

---

## 基本原則

- **データ構造の定義**のみを担当
- ロジックは含めない（純粋なデータコンテナ）
- プロパティは`readonly`で定義
- サフィックスは付けない（例: `AssetUpdate`, not `AssetUpdateData`）

---

## 配置場所

```
app/Domain/{Domain}/DTOs/
```

例:
- `app/Domain/Auth/DTOs/AssetUpdate.php`
- `app/Domain/Auth/DTOs/Maintenance.php`
- `app/Domain/Delivery/DTOs/DeliveryContent.php`

---

## 実装例

### シンプルなDTO

```php
namespace App\Domain\Auth\DTOs;

class AssetUpdate
{
    public function __construct(
        public readonly int $deployAssetId,
        public readonly string $hash,
    ) {}
}
```

### 複雑なDTO

```php
namespace App\Domain/Delivery/DTOs;

class DeliveryContent
{
    public function __construct(
        public readonly string $resourceType,
        public readonly int $resourceId,
        public readonly int $quantity,
        public readonly ?string $reason = null,
    ) {}
}
```

---

## 命名規約

- クラス名: `{概念名}` （サフィックスなし）
- プロパティ: キャメルケース、readonly
- ディレクトリ: `DTOs/` （複数形）

---

## チェックリスト

- [ ] `Domain/{Domain}/DTOs/`に配置
- [ ] プロパティは`public readonly`
- [ ] ロジックを含まない
- [ ] サフィックスなし命名
- [ ] コンストラクタプロモーション使用

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
