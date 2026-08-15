# ResourceDelivery Domain - リソース配送ドメイン設計書

## 概要

ResourceDeliveryドメインは、ゲーム内リソース（Diamond、Unit、Equipment、Item、Coinなど）の配送処理を統一的に扱うドメインです。

従来のDeliveryドメインを拡張し、**Resourceドメイン**と統合することで、より柔軟で拡張性の高い設計を実現しています。

## 主な変更点

### 旧Deliveryドメインとの違い

| 項目 | 旧Delivery | ResourceDelivery |
|------|-----------|------------------|
| リソース表現 | `DeliveryContent` (type, id, amount) | `Resource` + `ResourceDeliveryContent` |
| リソースタイプ | 5種類 (item, unit, equipment, diamond, wallet) | 27種類 (ResourceType Enum) |
| タイプ管理 | 文字列定数 (DeliveryConst) | Enum (ResourceType) |
| 拡張性 | 新タイプ追加時に定数追加 | Enumに追加するだけ |
| 型安全性 | 低い (文字列ベース) | 高い (Enum + 型ヒント) |

## アーキテクチャパターン

### Service + Manager パターン

```
ResourceDeliveryService
├── 役割: 配送処理の統括・実行
├── API: addResources() / deliver()
└── 依存: ResourceDeliveryManager, Handlers

ResourceDeliveryManager
├── 役割: 配送待ちコンテンツの状態管理
├── ライフサイクル: リクエストスコープ
└── 保持データ: needToSendContents, sendCompleteContents
```

## 使用パターン：遅延配送（Delayed Delivery Pattern）

### 推奨される使用方法

```php
use NexusResource\DataTransferObjects\Resource;

// Step 1: リソースを登録（複数箇所から登録可能）
$deliveryService->addResources([
    Resource::diamond(1000),
    Resource::gold(50000),
    Resource::unit('unit_hero_001', 1),
    Resource::equipment('equipment_sword_001', 1),
]);

// Step 2: まとめて配送を実行
$summary = $deliveryService->deliver($sysPlayerId);
```

### メリット

1. **柔軟な登録**: 複数のServiceから配送リソースを登録できる
2. **一括処理**: まとめて配送することで効率的
3. **トランザクション制御**: 配送実行時に一括でトランザクション管理
4. **追加報酬の連鎖**: レベルアップ報酬などの連鎖処理に対応

## コンポーネント設計

### 1. Resource (リソースDTO)

**責務:**
- ゲーム内リソースの基本データ構造
- ResourceTypeによる型安全なリソース表現

**主要メソッド:**

| メソッド | 説明 |
|---------|------|
| `Resource::diamond(int)` | Diamondリソースを作成 |
| `Resource::paidDiamond(int)` | 有償Diamondリソースを作成 |
| `Resource::gold(int, ?string)` | Goldリソースを作成 |
| `Resource::unit(string, int, ?int, ?int)` | Unitリソースを作成 |
| `Resource::item(string, int)` | Itemリソースを作成 |
| `getType()` | ResourceTypeを取得 |
| `getAmount()` | 数量を取得 |

### 2. ResourceDeliveryService（配送サービス）

**責務:**
- 配送処理の統括
- Handlerへの処理振り分け（Strategy Pattern）
- 配送結果のサマリー生成
- エラーハンドリング

**主要メソッド:**

| メソッド | 説明 |
|---------|------|
| `addResource(Resource)` | 単一リソースを登録 |
| `addResources(array\|Collection)` | 複数リソースを登録 |
| `deliver(int, ?ResourceDeliveryPolicy)` | 登録されたリソースを配送 |
| `getConvertedContentsWithoutSend()` | 配送前のプレビュー取得 |
| `getSupportedTypes()` | サポートするリソースタイプ一覧 |

### 3. ResourceDeliveryManager（配送マネージャー）

**責務:**
- 配送待ちコンテンツの保持・管理
- 配送完了コンテンツの追跡
- 状態遷移の管理

**ライフサイクル:**
- **リクエストスコープ**: 各リクエストごとに新規インスタンスを生成
- **自動クリア**: リクエスト終了時に自動的にクリアされる

**データ構造:**

```php
// 配送前コンテンツ
private array $needToSendContents = [
    'uniqueId1' => ResourceDeliveryContent,
    'uniqueId2' => ResourceDeliveryContent,
];

// 配送完了コンテンツ（クラスごとに分類）
private array $sendCompleteContents = [
    ResourceDeliveryContent::class => [ResourceDeliveryContent, ...],
];
```

### 4. Handlers（配送ハンドラー）

各リソースタイプごとに配送処理を実装：

| ハンドラー | 対応タイプ | 処理内容 |
|-----------|-----------|---------|
| `DiamondDeliveryHandler` | DIAMOND, PAID_DIAMOND | DiamondServiceでダイヤ付与 |
| `CurrencyDeliveryHandler` | GOLD, COIN | WalletServiceで通貨付与 |
| `BasicResourceDeliveryHandler` | FOOD, WOOD, STONE, IRON, STAMINA, EXPERIENCE | WalletServiceでリソース付与 |
| `ItemDeliveryHandler` | ITEM, CONSUMABLE, MATERIAL, TICKET, GACHA_TICKET | ItemServiceでアイテム付与 |
| `UnitDeliveryHandler` | UNIT | TrxUnitRepositoryでユニット作成 |
| `EquipmentDeliveryHandler` | EQUIPMENT, WEAPON, ARMOR, ACCESSORY | TrxEquipmentRepositoryで装備作成 |
| `PointsDeliveryHandler` | ALLIANCE_POINTS, PVP_POINTS等 | WalletServiceでポイント付与 |

**Strategy Pattern:**
- 各ハンドラーは`ResourceDeliveryHandlerInterface`を実装
- `supports(ResourceType|string)`: サポートするタイプを判定
- `handle(int, ResourceDeliveryContent)`: 実際の配送処理

### 5. DTOs（データ転送オブジェクト）

| DTO | 説明 |
|-----|------|
| `Resource` | リソースの基本データ（type, id, amount, metadata） |
| `ResourceDeliveryContent` | 配送コンテンツ（Resourceをラップ） |
| `ResourceDeliverySummary` | 配送結果のサマリー |
| `ResourceDeliveryComplete` | 配送完了データ |
| `ResourceDeliveryPolicy` | 配送ポリシー（エラーハンドリング制御） |

### 6. Enums（列挙型）

| Enum | 説明 | 主要値 |
|------|------|-------|
| `ResourceType` | リソースタイプ | DIAMOND, GOLD, UNIT, ITEM等（27種類） |
| `ResourceDeliveryStatus` | 配送ステータス | PENDING, DELIVERED, RECEIVED |
| `ResourceDeliveryMethod` | 配送方法 | NONE, SEND_TO_MAILBOX, THROW_ERROR... |
| `ResourceDeliveryResultReason` | 配送結果理由 | NONE, DUPLICATED_UNIT, RESOURCE_LIMIT_REACHED等 |

## ResourceType一覧

```php
// 通貨系
DIAMOND, PAID_DIAMOND, GOLD, COIN

// リソース系
FOOD, WOOD, STONE, IRON, STAMINA, EXPERIENCE

// アイテム系
ITEM, CONSUMABLE, MATERIAL, TICKET

// キャラクター・装備系
UNIT, EQUIPMENT, WEAPON, ARMOR, ACCESSORY

// ポイント系
ALLIANCE_POINTS, PVP_POINTS, EVENT_POINTS, ACHIEVEMENT_POINTS, VIP_POINTS

// その他
GACHA_TICKET, CUSTOM
```

## データフロー

```
1. 各Service/UseCase
   ↓ addResources()
2. ResourceDeliveryService
   ↓ (内部でResourceDeliveryManagerに登録)
3. ResourceDeliveryManager (needToSendContents)
   ↓ deliver() 実行
4. ResourceDeliveryService
   ↓ (タイプごとにグループ化)
5. 各Handler (実際の配送処理)
   ↓
6. ResourceDeliveryManager (sendCompleteContents)
   ↓
7. ResourceDeliverySummary (結果を返却)
```

## 実装例

### MailBoxでの使用例

```php
use NexusResource\DataTransferObjects\Resource;
use NexusResourceDelivery\Services\ResourceDeliveryService;

public function handle(int $sysPlayerId, int $trxMailboxId): ReceiveResponse
{
    return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId) {
        // メールボックスから添付物を取得
        $resources = $contentCollection->map(function ($content) {
            return match($content->getContentType()) {
                'diamond' => Resource::diamond($content->getAmount()),
                'gold' => Resource::gold($content->getAmount()),
                'unit' => Resource::unit($content->getContentId(), $content->getAmount()),
                default => null,
            };
        })->filter();

        // 配送処理（遅延配送パターン）
        if ($resources->count() > 0) {
            $this->deliveryService->addResources($resources);
            $deliverySummary = $this->deliveryService->deliver($sysPlayerId);
        }

        return new ReceiveResponse(...);
    });
}
```

### Gachaでの使用例

```php
use NexusResource\DataTransferObjects\Resource;

public function grantPrizes(int $sysPlayerId, array $prizes): void
{
    $resources = [];
    foreach ($prizes as $prize) {
        $resources[] = match($prize['content_type']) {
            'unit' => Resource::unit($prize['content_id'], 1),
            'equipment' => Resource::equipment($prize['content_id'], 1),
            'diamond' => Resource::diamond($prize['amount']),
            default => null,
        };
    }

    // 配送処理（遅延配送パターン）
    $this->deliveryService->addResources(array_filter($resources));
    $this->deliveryService->deliver($sysPlayerId);
}
```

## ディレクトリ構造

```
api/app/Domain/
├── Resource/
│   ├── DTOs/
│   │   └── Resource.php                    # リソースDTO
│   └── Enums/
│       └── ResourceType.php                # リソースタイプEnum（27種類）
│
└── ResourceDelivery/
    ├── README.md                           # このファイル
    ├── DTOs/
    │   ├── ResourceDeliveryContent.php     # 配送コンテンツ
    │   ├── ResourceDeliveryPolicy.php      # 配送ポリシー
    │   ├── ResourceDeliverySummary.php     # 配送結果サマリー
    │   ├── ResourceDeliveryComplete.php    # 配送完了データ
    │   └── ResourceDeliveryResult.php      # 配送結果
    ├── Enums/
    │   ├── ResourceDeliveryStatus.php      # 配送ステータス
    │   ├── ResourceDeliveryMethod.php      # 配送方法
    │   └── ResourceDeliveryResultReason.php # 配送結果理由
    ├── Handlers/
    │   ├── ResourceDeliveryHandlerInterface.php
    │   ├── DiamondDeliveryHandler.php
    │   ├── CurrencyDeliveryHandler.php
    │   ├── BasicResourceDeliveryHandler.php
    │   ├── ItemDeliveryHandler.php
    │   ├── UnitDeliveryHandler.php
    │   ├── EquipmentDeliveryHandler.php
    │   └── PointsDeliveryHandler.php
    ├── Managers/
    │   ├── ResourceDeliveryManager.php          # 状態管理（リクエストスコープ）
    │   └── ResourceDeliveryManagerInterface.php
    └── Services/
        └── ResourceDeliveryService.php          # 配送処理の統括
```

## 設計原則

### 1. 単一責任の原則（SRP）

- **Resource**: リソースデータの表現のみ
- **ResourceDeliveryService**: 配送処理の統括
- **ResourceDeliveryManager**: 状態管理のみ
- **各Handler**: 特定のリソースタイプの配送処理のみ

### 2. 開放閉鎖の原則（OCP）

- 新しいリソースタイプの追加: ResourceType Enumに追加 + 新しいHandlerを作成
- 既存コードの変更は最小限

### 3. 依存性逆転の原則（DIP）

- ResourceDeliveryServiceはHandlerの抽象（Interface）に依存
- ResourceDeliveryServiceはManagerの抽象（Interface）に依存

### 4. Strategy Pattern

- リソースタイプごとに異なる処理を適切なHandlerに振り分け
- Handlerの追加・削除が容易

## 今後の拡張予定

1. **リソース変換機能の実装**
   - 重複ユニット/装備の自動変換
   - ボックスアイテムの展開

2. **メールボックス連携の完全実装**
   - リソース上限超過時の自動メールボックス送信

3. **ログ記録機能**
   - 配送履歴の詳細なログ記録

4. **配送プレビュー機能の拡張**
   - チュートリアルガチャの引き直し機能対応

## 変更履歴

### 2026-07-16
- ResourceドメインとResourceDeliveryドメインを新規作成
- 旧DeliveryドメインからResourceDeliveryドメインへ移行
- ResourceType Enumを導入（27種類のリソースタイプ）
- Resource DTOを導入（型安全なリソース表現）
- 全DTOs、Enums、Managers、Servicesを移行完了
- **全Handlers実装完了（7種類）**:
  - DiamondDeliveryHandler
  - CurrencyDeliveryHandler
  - BasicResourceDeliveryHandler
  - ItemDeliveryHandler
  - UnitDeliveryHandler
  - EquipmentDeliveryHandler
  - PointsDeliveryHandler
- 構文テスト合格
