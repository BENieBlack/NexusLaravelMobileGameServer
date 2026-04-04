# Delivery Domain - 配送ドメイン設計書

## 概要

Deliveryドメインは、ゲーム内報酬（アイテム、ユニット、装備、ダイヤ、通貨など）の配送処理を統一的に扱うドメインです。

## アーキテクチャパターン

### Service + Manager パターン

```
DeliveryService
├── 役割: 配送処理の統括・実行
├── API: addContents() / deliver()
└── 依存: DeliveryManager, Handlers

DeliveryManager
├── 役割: 配送待ちコンテンツの状態管理
├── ライフサイクル: リクエストスコープ
└── 保持データ: needToSendContents, sendCompleteContents
```

## 使用パターン：遅延配送（Delayed Delivery Pattern）

### 推奨される使用方法

```php
// Step 1: 配送コンテンツを登録（複数箇所から登録可能）
$deliveryService->addContents([
    DeliveryContent::item('item_potion_001', 10),
    DeliveryContent::unit('unit_hero_001', 1),
    DeliveryContent::equipment('equipment_sword_001', 1),
]);

// Step 2: まとめて配送を実行
$summary = $deliveryService->deliver($sysPlayerId);
```

### メリット

1. **柔軟な登録**: 複数のServiceから配送コンテンツを登録できる
2. **一括処理**: まとめて配送することで効率的
3. **トランザクション制御**: 配送実行時に一括でトランザクション管理
4. **追加報酬の連鎖**: レベルアップ報酬などの連鎖処理に対応

## コンポーネント設計

### 1. DeliveryService（配送サービス）

**責務:**
- 配送処理の統括
- Handlerへの処理振り分け（Strategy Pattern）
- 配送結果のサマリー生成
- エラーハンドリング

**主要メソッド:**

| メソッド | 説明 |
|---------|------|
| `addContent(DeliveryContent)` | 単一コンテンツを登録 |
| `addContents(array\|Collection)` | 複数コンテンツを登録 |
| `deliver(int, ?DeliveryPolicy)` | 登録されたコンテンツを配送 |
| `getConvertedContentsWithoutSend()` | 配送前のプレビュー取得 |
| `getSupportedTypes()` | サポートするコンテンツタイプ一覧 |

### 2. DeliveryManager（配送マネージャー）

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
    'uniqueId1' => DeliveryContent,
    'uniqueId2' => DeliveryContent,
];

// 配送完了コンテンツ（クラスごとに分類）
private array $sendCompleteContents = [
    DeliveryContent::class => [DeliveryContent, ...],
];
```

### 3. Handlers（配送ハンドラー）

各リソースタイプごとに配送処理を実装：

| ハンドラー | 対応タイプ | 処理内容 |
|-----------|-----------|---------|
| `ItemDeliveryHandler` | item | アイテム付与 |
| `UnitDeliveryHandler` | unit | ユニット付与 |
| `EquipmentDeliveryHandler` | equipment | 装備付与 |
| `DiamondDeliveryHandler` | diamond | ダイヤ付与 |
| `WalletDeliveryHandler` | wallet | 通貨付与 |

**Strategy Pattern:**
- 各ハンドラーは`_BaseDeliveryHandlerInterface`を実装
- `supports(string $type)`: サポートするタイプを判定
- `handle(int $sysPlayerId, DeliveryContent $content)`: 実際の配送処理

### 4. DTOs（データ転送オブジェクト）

| DTO | 説明 |
|-----|------|
| `DeliveryContent` | 配送コンテンツ（type, id, amount, metadata） |
| `DeliverySummary` | 配送結果のサマリー |
| `DeliveryComplete` | 配送完了データ |
| `DeliveryPolicy` | 配送ポリシー（エラーハンドリング制御） |

### 5. Enums（列挙型）

| Enum | 説明 | 値 |
|------|------|---|
| `DeliveryStatus` | 配送ステータス | PENDING, COMPLETE, FAILED |
| `DeliveryMethod` | 配送方法 | NONE, SEND_TO_MAILBOX, THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED |
| `DeliveryResultReason` | 配送結果理由 | NONE, CONVERSION, FAILURE |

### 6. Constants（定数）

`DeliveryConst`: コンテンツタイプの定数定義

```php
CONTENT_TYPE_ITEM = 'item';
CONTENT_TYPE_UNIT = 'unit';
CONTENT_TYPE_EQUIPMENT = 'equipment';
CONTENT_TYPE_DIAMOND = 'diamond';
CONTENT_TYPE_WALLET = 'wallet';
```

## データフロー

```
1. 各Service/UseCase
   ↓ addContents()
2. DeliveryService
   ↓ (内部でDeliveryManagerに登録)
3. DeliveryManager (needToSendContents)
   ↓ deliver() 実行
4. DeliveryService
   ↓ (タイプごとにグループ化)
5. 各Handler (実際の配送処理)
   ↓
6. DeliveryManager (sendCompleteContents)
   ↓
7. DeliverySummary (結果を返却)
```

## 高度な機能

### 1. 報酬変換機能

**TODO（未実装）:**
- 重複ユニットの自動変換
- 重複装備の自動変換
- ボックスアイテムの展開

### 2. 追加報酬の連鎖処理

**実装済み:**
- 最大2回のループで追加報酬に対応
- 例: EXP配布 → レベルアップ → レベルアップ報酬

```php
// execDelivery() メソッド内で実装
for ($i = 0; $i < 2; $i++) {
    if ($this->deliveryManager->hasPendingContents() === false) {
        break;
    }
    $summary->merge($this->execDeliveryIteration($sysPlayerId, $policy));
}
```

### 3. ポリシーベースのエラーハンドリング

**デフォルトポリシー:**
- リソース上限超過時: メールボックスへ送信

**エラーモード:**
- リソース上限超過時: 例外を投げる

```php
// エラーモードの使用例
$policy = DeliveryPolicy::createThrowErrorWhenResourceLimitReachedPolicy(
    new GameException(GameErrorCode::RESOURCE_LIMIT_REACHED)
);
$summary = $deliveryService->deliver($sysPlayerId, $policy);
```

## 実装例

### MailBoxでの使用例

```php
// api/app/Domain/MailBox/UseCases/ReceiveUseCase.php
public function handle(int $sysPlayerId, int $trxMailboxId): ReceiveResponse
{
    return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId) {
        // メールボックスから添付物を取得
        $deliveryContentArray = $contentCollection->map(function ($content) {
            return new DeliveryContent(
                type: strtolower($content->getContentType()),
                id: $content->getContentId(),
                amount: $content->getAmount(),
            );
        })->toArray();

        // 配送処理（遅延配送パターン）
        if (count($deliveryContentArray) > 0) {
            $this->deliveryService->addContents($deliveryContentArray);
            $deliverySummary = $this->deliveryService->deliver($sysPlayerId);
        }

        return new ReceiveResponse(...);
    });
}
```

### Gachaでの使用例

```php
// api/app/Domain/Gacha/Services/GachaPrizeService.php
public function grantPrizes(int $sysPlayerId, array $prizes): void
{
    $deliveryContents = [];
    foreach ($prizes as $prize) {
        $deliveryContents[] = $this->createDeliveryContent(
            $prize['content_type'],
            $prize['content_id'],
            $prize['amount']
        );
    }

    // 配送処理（遅延配送パターン）
    $this->deliveryService->addContents($deliveryContents);
    $this->deliveryService->deliver($sysPlayerId);
}
```

## ディレクトリ構造

```
api/app/Domain/Delivery/
├── README.md                    # このファイル
├── Constants/
│   └── DeliveryConst.php       # コンテンツタイプ定数
├── DTOs/
│   ├── DeliveryComplete.php    # 配送完了データ
│   ├── DeliveryContent.php     # 配送コンテンツ
│   ├── DeliveryPolicy.php      # 配送ポリシー
│   └── DeliverySummary.php     # 配送結果サマリー
├── Enums/
│   ├── DeliveryMethod.php      # 配送方法
│   ├── DeliveryResultReason.php # 配送結果理由
│   └── DeliveryStatus.php      # 配送ステータス
├── Handlers/
│   ├── _BaseDeliveryHandlerInterface.php
│   ├── DiamondDeliveryHandler.php
│   ├── EquipmentDeliveryHandler.php
│   ├── ItemDeliveryHandler.php
│   ├── UnitDeliveryHandler.php
│   └── WalletDeliveryHandler.php
├── Managers/
│   ├── DeliveryManager.php          # 状態管理（リクエストスコープ）
│   └── DeliveryManagerInterface.php
└── Services/
    └── DeliveryService.php          # 配送処理の統括
```

## 設計原則

### 1. 単一責任の原則（SRP）

- **DeliveryService**: 配送処理の統括
- **DeliveryManager**: 状態管理のみ
- **各Handler**: 特定のリソースタイプの配送処理のみ

### 2. 開放閉鎖の原則（OCP）

- 新しいリソースタイプの追加: 新しいHandlerを追加するだけ
- 既存コードの変更は不要

### 3. 依存性逆転の原則（DIP）

- DeliveryServiceはHandlerの抽象（Interface）に依存
- DeliveryServiceはManagerの抽象（Interface）に依存

### 4. Strategy Pattern

- リソースタイプごとに異なる処理を適切なHandlerに振り分け
- Handlerの追加・削除が容易

## テスト

### テストカバレッジ

- **ユニットテスト**: 各Handler、DTO、Enumのテスト
- **統合テスト**: MailBox、Gachaでの実際の使用例

### テスト実行

```bash
./command/sail test
```

**結果**: 全191テスト成功（670アサーション）

## 今後の拡張予定

1. **報酬変換機能の実装**
   - 重複ユニット/装備の自動変換
   - ボックスアイテムの展開

2. **メールボックス連携の完全実装**
   - リソース上限超過時の自動メールボックス送信

3. **ログ記録機能**
   - 配送履歴の詳細なログ記録

4. **配送プレビュー機能の拡張**
   - チュートリアルガチャの引き直し機能対応

## 変更履歴

### 2026-04-04
- 命名規則の統一（`sendContents` → `deliver`など）
- DeliveryDelegatorの削除
- レガシーAPI（`delivers()`、`deliversImmediate()`など）の削除
- アーキテクチャを Service + Manager パターンに統一
- DeliveryManagerをリクエストスコープに設定（明示的にコメント追加）
- 遅延配送パターンの完全実装
