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
  "error_code": 1101,
  "message": "Stamina not enough. Required: 10, Current: 5"
}
```

### その他の例外（システムエラー）

```json
HTTP/1.1 500
{
  "error_code": 99999,
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

**エラーコード:** `401` (MASTER_DATA_NOT_FOUND), `16001` (PRODUCT_NOT_FOUND)

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

**エラーコード:** `10002` (PLAYER_NOT_FOUND), `12001` (UNIT_NOT_FOUND), `1901` (ITEM_NOT_ENOUGH), 他

---

### SystemDataException

システムデータ（`sys` database）が見つからない場合に使用。

**静的メソッド:**
- `SystemDataException::deploy(?int $deployId = null)` - デプロイ情報未検出
- `SystemDataException::playerDevice(int $deviceId)` - デバイス情報未検出
- `SystemDataException::playerToken(string $tokenHash)` - トークン未検出
- `SystemDataException::config(string $key)` - システム設定未検出
- `SystemDataException::generic(string $type, string|int $id)` - 汎用システムデータ未検出

**エラーコード:** `10001` (AUTHENTICATION_FAILED), `10003` (INVALID_TOKEN), `99999` (INTERNAL_ERROR)

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

**エラーコード:** `1001`/`1101`/`1901`/`1902` (リソース不足), `19004` 等 (各ドメインのビジネスロジックエラー)

---

## エラーコード一覧

エラーコードは層ごとに桁数で分ける。

| 層 | 桁数 | 定義場所 |
|---|---|---|
| インフラ | 3桁以下（1-999） | `App\Exceptions\InfraErrorCode` |
| パッケージ | 4桁（1000-9999） | 各パッケージの `*ErrorCode`（wallet 1000-1099 / stamina 1100-1199 / resource 1900-1999 …） |
| アプリケーション | 5桁（10000-99999） | `App\Exceptions\GameErrorCode` |

### 他レイヤーで定義（インフラ層・パッケージ層）

`GameErrorCode` には別名の定数だけを置き、値はそれぞれの層が持つ。
`GameErrorCode::STAMINA_NOT_ENOUGH` のように参照して構わないが、値を変えるときは定義元を直す。

| コード | 定数名 | 定義元 |
|---|---|---|
| 401 | `MASTER_DATA_NOT_FOUND` | `App\Exceptions\InfraErrorCode` |
| 1001 | `INSUFFICIENT_CURRENCY` | `LaravelWallet\Exceptions\WalletErrorCode::INSUFFICIENT_BALANCE` |
| 1003 | `WALLET_NOT_FOUND` | `LaravelWallet\Exceptions\WalletErrorCode` |
| 1101 | `STAMINA_NOT_ENOUGH` | `NexusStamina\Exceptions\StaminaErrorCode::INSUFFICIENT_STAMINA` |
| 1901 | `ITEM_NOT_ENOUGH` | `NexusResource\Exceptions\ResourceErrorCode::INSUFFICIENT_ITEM` |
| 1902 | `DIAMOND_NOT_ENOUGH` | `NexusResource\Exceptions\ResourceErrorCode::INSUFFICIENT_DIAMOND` |
| 1903 | `INVALID_ITEM_TYPE` | `NexusResource\Exceptions\ResourceErrorCode` |
| 1904 | `INVALID_RESOURCE_TYPE` | `NexusResource\Exceptions\ResourceErrorCode` |

### 認証関連 (10000-10999)

| コード | 定数名 |
|---|---|
| 10001 | `AUTHENTICATION_FAILED` |
| 10002 | `PLAYER_NOT_FOUND` |
| 10003 | `INVALID_TOKEN` |
| 10004 | `DEVICE_ALREADY_EXISTS` |

### プレイヤー関連 (11000-11999)

| コード | 定数名 |
|---|---|
| 11001 | `PLAYER_DATA_CORRUPTED` |
| 11002 | `PLAYER_NAME_INVALID` |
| 11003 | `PLAYER_LEVEL_MAX_REACHED` |

### ユニット関連 (12000-12999)

| コード | 定数名 |
|---|---|
| 12001 | `UNIT_NOT_FOUND` |
| 12002 | `UNIT_MAX_LEVEL_REACHED` |
| 12003 | `UNIT_EVOLUTION_FAILED` |

### 装備関連 (13000-13999)

| コード | 定数名 |
|---|---|
| 13001 | `EQUIPMENT_NOT_FOUND` |
| 13002 | `EQUIPMENT_MAX_LEVEL_REACHED` |
| 13003 | `EQUIPMENT_ENHANCE_FAILED` |

### クエスト関連 (14000-14999)

| コード | 定数名 |
|---|---|
| 14001 | `QUEST_NOT_FOUND` |
| 14002 | `QUEST_NOT_AVAILABLE` |
| 14003 | `QUEST_ALREADY_COMPLETED` |

### バトル関連 (15000-15999)

| コード | 定数名 |
|---|---|
| 15001 | `PARTY_FORMATION_INVALID` |
| 15002 | `BATTLE_RESULT_INVALID` |

### アプリ内課金関連 (16000-16999)

| コード | 定数名 |
|---|---|
| 16001 | `PRODUCT_NOT_FOUND` |
| 16002 | `PRODUCT_INACTIVE` |
| 16003 | `PURCHASE_LIMIT_EXCEEDED` |
| 16004 | `INVALID_PRODUCT_TYPE` |
| 16005 | `PRODUCT_ID_MISMATCH` |
| 16006 | `RECEIPT_VERIFICATION_FAILED` |
| 16007 | `PRICE_MISMATCH` |

### フレンド関連 (17000-17999)

| コード | 定数名 |
|---|---|
| 17001 | `FRIEND_REQUEST_ALREADY_EXISTS` |
| 17002 | `FRIEND_ALREADY_EXISTS` |
| 17003 | `FRIEND_REQUEST_NOT_FOUND` |
| 17004 | `TARGET_PLAYER_NOT_FOUND` |
| 17005 | `CANNOT_SEND_FRIEND_REQUEST_TO_SELF` |
| 17006 | `FRIEND_APPLY_NOT_FOUND` |
| 17007 | `NOT_AUTHORIZED_TO_ACCEPT` |
| 17008 | `FRIEND_APPLY_ALREADY_ACCEPTED` |
| 17009 | `FRIEND_APPLY_ALREADY_DELETED` |
| 17010 | `CANNOT_DELETE_SELF` |
| 17011 | `FRIEND_NOT_FOUND` |
| 17012 | `NOT_AUTHORIZED_TO_REJECT` |
| 17013 | `FRIEND_APPLY_ALREADY_REJECTED` |

### ギルド関連 (18000-18999)

| コード | 定数名 |
|---|---|
| 18001 | `GUILD_NOT_FOUND` |
| 18002 | `GUILD_NAME_ALREADY_EXISTS` |
| 18003 | `GUILD_FULL` |
| 18004 | `PLAYER_ALREADY_IN_GUILD` |
| 18005 | `PLAYER_NOT_IN_GUILD` |
| 18006 | `GUILD_APPLY_ALREADY_EXISTS` |
| 18007 | `GUILD_APPLY_NOT_FOUND` |
| 18008 | `GUILD_INVALID_STATUS` |
| 18009 | `GUILD_PERMISSION_DENIED` |
| 18010 | `GUILD_MASTER_CANNOT_LEAVE` |
| 18011 | `GUILD_MEMBER_NOT_FOUND` |
| 18012 | `GUILD_CREATE_FAILED` |
| 18013 | `GUILD_APPLY_FAILED` |
| 18014 | `GUILD_APPLY_ACCEPT_FAILED` |
| 18015 | `GUILD_APPLY_REJECT_FAILED` |
| 18016 | `GUILD_LEAVE_FAILED` |

### ガチャ関連 (19000-19999)

| コード | 定数名 |
|---|---|
| 19001 | `GACHA_NOT_FOUND` |
| 19002 | `GACHA_INACTIVE` |
| 19003 | `GACHA_NOT_AVAILABLE` |
| 19004 | `GACHA_DAILY_LIMIT_EXCEEDED` |
| 19005 | `GACHA_COST_NOT_FOUND` |
| 19006 | `GACHA_STEP_NOT_FOUND` |
| 19007 | `GACHA_CANDIDATE_NOT_FOUND` |
| 19008 | `GACHA_CANDIDATE_REQUIRED` |
| 19009 | `GACHA_INVALID_DRAW_COUNT` |
| 19010 | `GACHA_NO_PRIZES_AVAILABLE` |

### メールボックス関連 (20000-20999)

| コード | 定数名 |
|---|---|
| 20001 | `MAILBOX_NOT_FOUND` |
| 20002 | `MAILBOX_ALREADY_RECEIVED` |
| 20003 | `MAILBOX_NOT_OPENED` |

### アプリケーション汎用 (99000-99999)

| コード | 定数名 |
|---|---|
| 99001 | `INVALID_PARAMETER` |
| 99002 | `VALIDATION_FAILED` |
| 99900 | `NOT_IMPLEMENTED` |
| 99999 | `INTERNAL_ERROR` |

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
