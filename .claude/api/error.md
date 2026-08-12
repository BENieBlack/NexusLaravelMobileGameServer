# エラーハンドリング

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、エラーハンドリングのルールと例外階層を定義します。

---

## 目次

- [基本原則](#基本原則)
- [エラーレスポンス形式](#エラーレスポンス形式)
- [例外階層](#例外階層)
- [例外クラス一覧](#例外クラス一覧)
- [エラーコード一覧](#エラーコード一覧)
- [使用例](#使用例)

---

## 基本原則

1. **データソース別の例外クラス**を使用
   - マスターデータ → `MasterDataException`
   - トランザクションデータ → `TransactionDataException`
   - システムデータ → `SystemDataException`
   - ビジネスロジック → `BusinessLogicException`

2. **静的ファクトリーメソッド**でエラー生成
   ```php
   // ✅ Good: 明確で再利用可能
   throw MasterDataException::unit($mstUnitId);
   throw TransactionDataException::player($playerId);
   throw BusinessLogicException::itemNotEnough($itemId, $required, $current);
   ```

3. **一貫したレスポンス形式**
   - すべてのエラーで `{error_code: int, message: string}` 形式
   - GameException系: HTTP 999
   - その他: 適切なHTTPステータスコード

---

## エラーレスポンス形式

### GameException（ビジネスロジックエラー）

```json
HTTP/1.1 999
{
  "error_code": 10100,
  "message": "Stamina not enough. Required: 10, Current: 5"
}
```

### その他の例外（システムエラー）

```json
HTTP/1.1 500
{
  "error_code": 19999,
  "message": "Internal server error"
}
```

---

## 例外階層

```
Exception (PHP標準)
├── GameException (ゲーム共通基底例外)
│   ├── MasterDataException (マスターデータ未検出)
│   ├── TransactionDataException (トランザクションデータ未検出)
│   ├── SystemDataException (システムデータ未検出)
│   └── BusinessLogicException (ビジネスロジックエラー)
└── その他の標準例外
```

### 各例外クラスの責務

| 例外クラス | データベース | 用途 | 例 |
|-----------|------------|------|-----|
| `MasterDataException` | `mst` | マスターデータが見つからない | ユニット定義、アイテム定義、レベルテーブル |
| `TransactionDataException` | `trx1`, `trx2` | プレイヤー所有データが見つからない | ユニット所持、アイテム所持、装備 |
| `SystemDataException` | `sys` | システムデータが見つからない | プレイヤー情報、デバイス情報、トークン |
| `BusinessLogicException` | - | ビジネスルール違反 | スタミナ不足、アイテム不足、購入制限超過 |

---

## 例外クラス一覧

### MasterDataException

マスターデータ（`mst` database）が見つからない場合に使用。

**静的メソッド:**
- `MasterDataException::unit(string $mstUnitId)` - ユニットマスター未検出
- `MasterDataException::item(string $mstItemId)` - アイテムマスター未検出
- `MasterDataException::unitLevel(string $rarity, int $level)` - レベルマスター未検出
- `MasterDataException::playerLevel(int $level)` - プレイヤーレベルマスター未検出
- `MasterDataException::product(string $mstProductId)` - 商品マスター未検出
- `MasterDataException::inAppPurchase(string $mstInAppPurchaseId)` - 課金商品マスター未検出
- `MasterDataException::generic(string $type, string|int $id)` - 汎用マスターデータ未検出

**エラーコード:** `10401` (MASTER_DATA_NOT_FOUND), `10200` (PRODUCT_NOT_FOUND)

---

### TransactionDataException

トランザクションデータ（`trx1`, `trx2` database）が見つからない場合に使用。

**静的メソッド:**
- `TransactionDataException::player(int $playerId)` - プレイヤー未検出
- `TransactionDataException::playerByUuid(string $uuid)` - UUID検索でプレイヤー未検出
- `TransactionDataException::playerByMyId(string $myId)` - My ID検索でプレイヤー未検出
- `TransactionDataException::unit(int $unitId)` - ユニット未検出
- `TransactionDataException::item(string $itemId)` - アイテム未検出
- `TransactionDataException::equipment(int $equipmentId)` - 装備未検出
- `TransactionDataException::wallet(string $itemId)` - ウォレット未検出
- `TransactionDataException::diamond(int $playerId, string $platform)` - ダイヤモンドデータ未検出
- `TransactionDataException::stamina(int $playerId)` - スタミナデータ未検出
- `TransactionDataException::generic(string $type, string|int $id)` - 汎用トランザクションデータ未検出

**エラーコード:** `10002` (PLAYER_NOT_FOUND), `10400` (UNIT_NOT_FOUND), `10102` (ITEM_NOT_ENOUGH), 他

---

### SystemDataException

システムデータ（`sys` database）が見つからない場合に使用。

**静的メソッド:**
- `SystemDataException::deploy(?int $deployId = null)` - デプロイ情報未検出
- `SystemDataException::playerDevice(int $deviceId)` - デバイス情報未検出
- `SystemDataException::playerToken(string $tokenHash)` - トークン未検出
- `SystemDataException::config(string $key)` - システム設定未検出
- `SystemDataException::generic(string $type, string|int $id)` - 汎用システムデータ未検出

**エラーコード:** `10001` (AUTHENTICATION_FAILED), `10003` (INVALID_TOKEN), `19999` (INTERNAL_ERROR)

---

### BusinessLogicException

ビジネスルール違反の場合に使用。

**静的メソッド:**
- `BusinessLogicException::staminaNotEnough(int $required, int $current)` - スタミナ不足
- `BusinessLogicException::diamondNotEnough(int $required, int $current)` - ダイヤモンド不足
- `BusinessLogicException::itemNotEnough(string $itemId, int $required, int $current)` - アイテム不足
- `BusinessLogicException::insufficientCurrency(string $currencyId, int $required, int $current)` - 通貨不足
- `BusinessLogicException::invalidItemType(string $itemId, string $expectedType, string $actualType)` - アイテムタイプ不正
- `BusinessLogicException::unitMaxLevelReached(int $unitId, int $maxLevel)` - 最大レベル到達
- `BusinessLogicException::purchaseLimitExceeded(string $productId, int $limit)` - 購入制限超過
- `BusinessLogicException::productInactive(string $productId)` - 商品無効
- `BusinessLogicException::invalidResourceType(string $resourceType)` - リソースタイプ不正
- `BusinessLogicException::invalidProductType(string $productType)` - 商品タイプ不正

**エラーコード:** `10100`-`10599` (リソース不足、ビジネスロジックエラー)

---

## エラーコード一覧

### 認証関連 (10000-10099)

| コード | 定数名 | 説明 |
|-------|--------|------|
| 10001 | `AUTHENTICATION_FAILED` | 認証失敗 |
| 10002 | `PLAYER_NOT_FOUND` | プレイヤーが見つからない |
| 10003 | `INVALID_TOKEN` | トークンが無効 |

### リソース不足 (10100-10199)

| コード | 定数名 | 説明 |
|-------|--------|------|
| 10100 | `STAMINA_NOT_ENOUGH` | スタミナ不足 |
| 10101 | `DIAMOND_NOT_ENOUGH` | ダイヤモンド不足 |
| 10102 | `ITEM_NOT_ENOUGH` | アイテム不足 |

### アプリ内課金 (10200-10299)

| コード | 定数名 | 説明 |
|-------|--------|------|
| 10200 | `PRODUCT_NOT_FOUND` | 商品が見つからない |
| 10201 | `PRODUCT_INACTIVE` | 商品が無効 |
| 10202 | `PURCHASE_LIMIT_EXCEEDED` | 購入制限超過 |
| 10203 | `INVALID_PRODUCT_TYPE` | 商品タイプが無効 |

### データ検証 (10300-10399)

| コード | 定数名 | 説明 |
|-------|--------|------|
| 10300 | `INVALID_PARAMETER` | パラメータが無効 |
| 10301 | `VALIDATION_FAILED` | バリデーション失敗 |

### リソース未検出 (10400-10499)

| コード | 定数名 | 説明 |
|-------|--------|------|
| 10400 | `UNIT_NOT_FOUND` | ユニットが見つからない |
| 10401 | `MASTER_DATA_NOT_FOUND` | マスターデータが見つからない |
| 10402 | `EQUIPMENT_NOT_FOUND` | 装備が見つからない |
| 10403 | `WALLET_NOT_FOUND` | ウォレットが見つからない |

### ビジネスロジック (10500-10599)

| コード | 定数名 | 説明 |
|-------|--------|------|
| 10500 | `INSUFFICIENT_CURRENCY` | 通貨不足 |
| 10501 | `INVALID_ITEM_TYPE` | アイテムタイプが無効 |
| 10502 | `UNIT_MAX_LEVEL_REACHED` | ユニット最大レベル到達 |
| 10503 | `INVALID_RESOURCE_TYPE` | リソースタイプが無効 |

### システムエラー (19900-19999)

| コード | 定数名 | 説明 |
|-------|--------|------|
| 19900 | `NOT_IMPLEMENTED` | 未実装 |
| 19999 | `INTERNAL_ERROR` | 内部エラー |

---

## 使用例

### UseCase での使用

```php
use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;
use App\Exceptions\BusinessLogicException;

class UnitLevelUpUseCase
{
    public function handle(int $playerId, int $unitId, string $itemId, int $useCount): array
    {
        return $this->executeWithTransaction(function () use ($playerId, $unitId, $itemId, $useCount) {
            // 1. トランザクションデータのチェック
            $unit = $this->trxUnitRepository->findById($unitId);
            if (!$unit) {
                throw TransactionDataException::unit($unitId);
            }

            // 2. マスターデータのチェック
            $mstItem = $this->mstItemRepository->selectById($itemId);
            if (!$mstItem) {
                throw MasterDataException::item($itemId);
            }

            // 3. ビジネスロジックのチェック
            $currentAmount = $this->trxItemRepository->getItemAmount($playerId, $itemId);
            if ($currentAmount < $useCount) {
                throw BusinessLogicException::itemNotEnough($itemId, $useCount, $currentAmount);
            }

            // 処理実行...
        });
    }
}
```

### Service での使用

```php
use App\Exceptions\MasterDataException;
use App\Exceptions\TransactionDataException;

class UnitLevelService
{
    public function addExp(int $unitId, int $exp): array
    {
        $unit = $this->trxUnitRepository->findById($unitId);
        if (!$unit) {
            throw TransactionDataException::unit($unitId);
        }

        $mstUnit = $this->mstUnitRepository->selectById($unit->mst_unit_id);
        if (!$mstUnit) {
            throw MasterDataException::unit($unit->mst_unit_id);
        }

        // レベルアップ処理...
    }
}
```

### Controller での使用

```php
use App\Exceptions\GameException;
use App\Exceptions\GameErrorCode;

class UnitController extends _BaseController
{
    public function levelUp(UnitLevelUpRequest $request): JsonResponse
    {
        return $this->execute(function () use ($request) {
            $playerId = $request->getAuthenticatedPlayerId();
            
            if (!$playerId) {
                throw new GameException(
                    GameErrorCode::AUTHENTICATION_FAILED,
                    'Player ID not found in request'
                );
            }

            // UseCase実行...
        });
    }
}
```

---

## 設計の利点

1. **障害対応の効率化**
   - エラーログから即座にデータソースを特定可能
   - `MasterDataException` → マスターデータ修正
   - `TransactionDataException` → データ不整合調査
   - `SystemDataException` → システム設定確認

2. **可読性の向上**
   ```php
   // ❌ Before: 何のデータが見つからないのか不明確
   throw ResourceNotFoundException::generic('unit', $unitId);
   
   // ✅ After: トランザクションデータのユニットが見つからないと明確
   throw TransactionDataException::unit($unitId);
   ```

3. **エラーハンドリングの統一**
   - すべての例外が `GameException` を継承
   - 一貫したレスポンス形式
   - エラーコードの体系的管理

4. **テスト・デバッグの容易化**
   - 例外の種類でモックの使い分けが可能
   - エラー発生箇所の特定が迅速

---

## 関連ドキュメント

- [API設計](../api.md) - API全体の設計方針
- [アーキテクチャ](../architecture.md) - レイヤー構造と依存関係
- [データベース設計](../database.md) - データベース構成

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
