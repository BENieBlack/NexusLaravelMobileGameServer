# Interface 設計ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、Interfaceの設計ルールを定義します。

---

## 基本原則

- **契約（Contract）を定義**
- Strategy Patternでの実装切り替えに使用
- 依存性の逆転原則（DIP）を実現

---

## 実装例

### Strategy Pattern用Interface

```php
namespace App\Domain\Delivery\Handlers;

use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\DTOs\DeliveryResult;

interface DeliveryHandlerInterface
{
    /**
     * このHandlerが処理可能かどうか
     */
    public function canHandle(DeliveryContent $content): bool;
    
    /**
     * 配布処理を実行
     */
    public function deliver(int $playerId, DeliveryContent $content): DeliveryResult;
}
```

### 実装例

```php
namespace App\Domain\Delivery\Handlers;

class ItemDeliveryHandler implements DeliveryHandlerInterface
{
    public function canHandle(DeliveryContent $content): bool
    {
        return $content->resourceType === 'item';
    }
    
    public function deliver(int $playerId, DeliveryContent $content): DeliveryResult
    {
        // アイテム配布ロジック
        return new DeliveryResult(/* ... */);
    }
}
```

---

## 命名規約

- Interface名: `{機能}Interface`
- メソッド: 動詞で始める（`canHandle`, `deliver`等）

---

## チェックリスト

- [ ] 契約を明確に定義
- [ ] Strategy Patternで使用
- [ ] 実装クラスが複数存在

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
