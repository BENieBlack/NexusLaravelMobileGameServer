# Service移行提案：Domain層からパッケージ層へ

## 概要

現在、`api/app/Domain/*/Services/`に配置されているServiceの中で、パッケージ層に移動すべきものを整理し、移行計画を提案します。

## 背景

### 現在の問題点

1. **ビジネスロジックがアプリケーション層に混在**
   - Domain層（`api/app/Domain`）にビジネスロジックが散在
   - パッケージ（`packages/nexus-*`）が存在するのに利用されていない

2. **再利用性の低下**
   - 他のアプリケーション（管理画面、CLI、Job等）から同じロジックを使えない
   - Eloquent Modelに強く依存している

3. **テストの困難さ**
   - HTTPに依存したテストが必要
   - 純粋なビジネスロジックのテストが困難

### 理想的な設計

```
┌─────────────────────────────────┐
│ Domain Layer (api/app/Domain)   │
│ - UseCases                      │
│ - Service Wrappers (DTO ↔ Model) │
└────────────┬────────────────────┘
             │ 依存
┌────────────▼────────────────────┐
│ Package Layer (packages/nexus-*) │
│ - Domain Services (DTO使用)     │
│ - Business Logic                │
│ - Repository Interfaces         │
└─────────────────────────────────┘
```

## 移行対象のService分析

### ✅ すでに適切に分離されているもの（変更不要）

| Domain Service | Package Service | 評価 |
|---|---|---|
| `StaminaService` | `nexus-stamina/Services/StaminaService` | ✅ 完璧。DTO ↔ Model変換のラッパー |
| `WalletService` | LaravelWallet package | ✅ 完璧。Facadeパターン |
| `GachaDrawService` | `nexus-gacha/Services/GachaDrawService` | ✅ 完璧。配列変換ラッパー |
| `UnitLevelService` | `nexus-level/Services/_BaseLevelService` | ✅ 継承パターン、適切 |
| `VersionService` | `nexus-version/Services/VersionService` | ✅ 完璧。Wrapperパターン適用済み |

### ✅ オーケストレーション層として適切（移行不要）

| Domain Service | 評価 | 理由 |
|---|---|---|
| `InAppPurchaseDiamondService` | ✅ 適切 | 複数Serviceを組み合わせたワークフロー（UseCaseに近い責務） |
| `GachaCostService` | ✅ 適切 | InAppPurchaseDiamondBalanceService/ItemServiceへの委譲のみ（オーケストレーション） |

> `DiamondService`（後方互換用のFacade）は2026-08-20に削除した。委譲するだけで
> 呼び出し段数を増やしていただけであり、「Domain層にクラスを作る基準」を満たさない。
> 呼び出し元は `InAppPurchaseDiamondBalanceService` / `InAppPurchaseDiamondService` を直接使う。

### 🔄 パッケージに移動すべきService

#### ✅ 完了：ItemService（パッケージ移行）

**移行完了日:** 2026-08-10（コミット `f4fd003`）
**Domain層の再統合:** 2026-08-19

**現在の構造:**

```
packages/nexus-resource/src/Services/
  ├── ItemService.php        # アイテム残高（取得・加算・有償優先消費）
  └── DiamondService.php     # ダイヤ残高（取得・加算・無償→有償消費）

api/app/Domain/Item/Services/
  └── ItemService.php        # ラッパー：パッケージへの委譲 + DTO → TrxItem 変換
```

**移行したビジネスロジック:**

- ✅ 有償優先消費ロジック（`consumeWithPaidFirst`）
- ✅ 残高チェック（`validateSufficientAmount`）
- ✅ 加算/減算のビジネスルール（`addItem` / `consumeItem`）

**後方互換性:** ✅ 既存コードは変更不要（Domain層ラッパーで維持）

##### Domain層のRead/Write分割は取りやめた

当初はDomain層にも `ItemReadService` / `ItemWriteService` を置き、`ItemService` を
後方互換用のFacadeとして `@deprecated` にしていたが、2026-08-19に `ItemService` へ再統合した。

取りやめた理由:

1. **誰も使わなかった** — `@deprecated` にしたにもかかわらず、呼び出し元5箇所すべてが
   `ItemService` を使い続け、`ItemReadService` / `ItemWriteService` の外部参照は0件だった
2. **分割の前提が無い** — 読み書き分離が効くのは読みと書きでモデルや保存先が異なる場合
   （リードレプリカ、参照用の非正規化モデル）。Itemは両方とも同じ `ItemRepositoryInterface`・
   同じ `trx_item`・同じシャードを見るため、分けても得るものがない
3. **他のドメインに広がらなかった** — Domain層の他22Serviceは分割しておらず、Itemだけが例外だった
4. **委譲が4段になっていた** — UseCase → ItemService → Item{Read,Write}Service →
   パッケージService → Repository

##### パッケージ層のRead/Write分割も取りやめた（2026-08-20）

当初は「他アプリから読み取りだけを使うなら依存を絞れる」としてパッケージ側の分割を残したが、
`ItemReadService` / `ItemWriteService` はどちらも同じ `ItemRepositoryInterface` を要求するため、
分けても絞れる依存が無かった。`NexusResource\Services\ItemService` に統合した。

##### Domain層にクラスを作る基準

パッケージ層に処理があるとき、Domain層にクラスを作るのは次のいずれかに当てはまる場合に限る。

| 基準 | 例 |
|---|---|
| Eloquent Modelを返す必要がある呼び出し元との変換 | `ItemService::consumeItem`（DTO → TrxItem） |
| 複数パッケージにまたがるオーケストレーション | `GachaCostService` |
| アプリ固有の例外への翻訳 | `VersionService`（`SystemDataException`） |

**どれにも当てはまらないなら、Domain層のクラスを作らずUseCaseからパッケージを直接呼ぶ。**
委譲するだけのクラスは、呼び出し段数を増やすだけで何も足していない。


#### 優先度：中

##### ✅ 完了：DiamondBalanceService

**移行完了日:** 2026-08-10  
**コミット:** f7c9c83

**移行後の構造:**
- パッケージ層: `nexus-resource/src/Services/DiamondService.php` - DTOベースのビジネスロジック
- Domain層: `api/app/Domain/InAppPurchase/Services/InAppPurchaseDiamondBalanceService.php` - Wrapperパターン + FIFO管理追加機能
- Repository実装: `api/app/Repositories/Trx/DiamondRepositoryAdapter.php`

##### ダイヤの残高ロジックはnexus-resourceへ移した（2026-08-20）

当初は `nexus-core-billing` に置いていたが、`nexus-resource` へ移設した。

1. **ダイヤは課金専用ではない** — ガチャ消費（`GachaCostService`）・報酬配布（`DiamondDeliveryHandler`）
   からも増減する。`nexus-resource` の `ResourceType` には元から `DIAMOND` / `PAID_DIAMOND` があった
2. **月次集計の大元をライブラリ側に置く** — 残高の加減算が1箇所に集まるため、集計・突合の起点が明確になる
3. **パッケージ→アプリの逆依存が消えた** — `nexus-resource-delivery` の
   `DiamondDeliveryHandler` / `ItemDeliveryHandler` が `App\Domain\...` をimportしていたが、
   パッケージ層のServiceを直接使う形になった

移設したもの: `Services/DiamondService`（旧 `DiamondBalanceService`）、
`Contracts/DiamondRepositoryInterface`、`DataTransferObjects/DiamondBalance`。

**課金固有の処理は `nexus-core-billing` に残す** — レシート検証（`AppStore` / `GooglePlay`）、
購入制限、冪等性チェック。FIFO用の `trx_diamond_balance` レコード作成（単価・返金計算に使う）は
Eloquentに依存するためDomain層の `addPaidDiamondWithBalance()` に残している。

**移行したビジネスロジック:**
- ✅ 無償→有償消費ロジック（consumeFreeThenPaidDiamond）
- ✅ 有償のみ消費ロジック（consumePaidDiamond）
- ✅ 残高チェック（validatePaidBalance, validateTotalBalance）
- ✅ 加算ロジック（addDiamond）

**Domain層に残した追加機能:**
- addPaidDiamondWithBalance() - FIFO管理用バランスレコード作成（アプリ固有機能）

**後方互換性:** ✅ 既存コードは変更不要（Domain層Wrapperで維持）

---

## 移行完了サマリー（2026-08-10）

### ✅ 移行完了したService

| Service | パッケージ | コミット | 移行したロジック |
|---|---|---|---|
| ItemService系 | nexus-resource | f4fd003 | 有償優先消費、残高チェック、加算/減算 |
| DiamondBalanceService | nexus-core-billing | f7c9c83 | 無償→有償消費、有償のみ消費、残高チェック、加算 |

### 📊 移行効果

**再利用性向上:**
- パッケージ化により他プロジェクトでも利用可能
- DTO基盤で疎結合を実現

**テスタビリティ向上:**
- パッケージ層は純粋なPHPユニットテスト可能
- Modelに依存しないテストが可能

**保守性向上:**
- ビジネスロジックが一箇所に集約
- 関心の分離により変更影響範囲を最小化

**後方互換性:**
- 既存コード変更不要（Domain層Wrapperで維持）
- 段階的な移行が可能

### 🎯 適用したアーキテクチャパターン

**Wrapperパターン:**
```
Package層（DTO） → ビジネスロジック
Domain層（Model） → DTO ↔ Model変換 + アプリ固有機能
```

**利点:**
- Package層: 再利用可能な純粋なロジック
- Domain層: アプリ固有の追加機能（FIFO管理等）

---

## 今後の移行対象候補（優先度低）

**判定結果: 現状のまま維持推奨**

以下のServiceは現在適切な設計となっているため、移行不要と判断：

### GachaCostService / GachaValidationService

**現状:**
- `GachaDrawService` / `GachaPrizeService` / `GachaProgressService` はすでに移行済み ✅
- `GachaCostService` はDomain層に残存

**判定:**
- ✅ **移行不要** - オーケストレーション層として適切
- InAppPurchaseDiamondBalanceService/ItemServiceへの委譲のみ（ビジネスロジックなし）
- UseCaseに近い責務

### InAppPurchase関連Service

**現状:**
- `InAppPurchaseDiamondBalanceService`（Domain層ラッパー）は移行完了 ✅
- `InAppPurchaseDiamondService` はDomain層に残存

**判定:**
- ✅ **移行不要** - オーケストレーション層として適切
- 複数Serviceを組み合わせたワークフロー管理（UseCaseに近い責務）
- InAppPurchaseValidationService、InAppPurchaseDiamondBalanceService、InAppPurchaseHistoryServiceへの委譲

### VersionService

**現状:**
- Domain層: `api/app/Domain/Version/Services/VersionService.php`
- Package層: `packages/nexus-version/src/Services/VersionService.php`

**判定:**
- ✅ **すでに移行済み** - Wrapperパターン適用済み
- パッケージServiceへの委譲 + Eloquent Model変換
- 追加の作業不要

---

## 移行手順（参考：ItemServiceの例）

### Step 1: パッケージにServiceを作成

1. DTOを確認・拡張
2. Repository Interfaceを作成
3. ServiceをDTOベースで実装
4. テストを作成（純粋なPHPテスト）

### Step 2: Domain層をラッパー化

1. パッケージServiceをDI
2. DTOをEloquent Modelに変換するロジックのみ残す
3. ビジネスロジックはすべてパッケージに委譲

変換もオーケストレーションも例外翻訳も無いなら、**このStepを飛ばして
UseCaseからパッケージを直接呼ぶ**。判断基準は「Domain層にクラスを作る基準」を参照。

### Step 3: テスト更新

1. パッケージServiceの単体テスト
2. Domain ラッパーの統合テスト
3. UseCaseのE2Eテスト

### Step 4: 既存コード更新

1. UseCaseからの呼び出し確認
2. 他のServiceからの依存確認
3. 段階的に移行

## 設計パターン

### パターン1: ラッパーパターン（推奨）

**用途:** ビジネスロジックはパッケージ、Eloquent変換はDomain層

```php
// Package Layer (DTO使用)
class StaminaService {
    public function consumeStamina(int $playerId, int $amount): Stamina;
}

// Domain Layer (Model返却)
class StaminaService {
    public function __construct(
        private readonly BaseStaminaService $baseService
    ) {}
    
    public function consumeStamina(int $playerId, int $amount): array {
        // パッケージServiceを呼び出し
        $dto = $this->baseService->consumeStamina($playerId, $amount);
        
        // 配列に変換して返却（HTTPレスポンス用）
        return $dto->toArray();
    }
}
```

**メリット:**
- ✅ 疎結合
- ✅ パッケージが完全に独立
- ✅ テストしやすい

### パターン2: オーケストレーション

**用途:** 複数のServiceを順番に呼んで1つの業務フローを完成させる場合

```php
// Domain Layer
class InAppPurchaseDiamondService {
    public function __construct(
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
        private readonly InAppPurchaseValidationService $validationService,
        private readonly InAppPurchaseDiamondBalanceService $diamondBalanceService,
        private readonly InAppPurchaseHistoryService $historyService,
    ) {}

    public function purchaseDiamond(...): array {
        // 1. 購入制限チェック → 2. ダイヤ加算 → 3. 履歴更新 → 4. 残高返却
    }
}
```

**⚠️ Facadeにしないこと。** 「後方互換のために既存クラス名を残し、中身は新Serviceへ丸投げ」は
オーケストレーションではない。呼び出し元を新Serviceに向け直して、古いクラスは削除する。
`ItemService`のRead/Write分割（2026-08-19）と`DiamondService`（2026-08-20）はこれで削除した。

### パターン3: 継承パターン（限定的に使用）

**用途:** Template Methodパターンで共通ロジックを提供

```php
// Package Layer (抽象クラス)
abstract class _BaseLevelService {
    abstract protected function getEntity(mixed $id): object;
    abstract protected function updateEntity(object $entity, int $level, int $exp): void;
    
    public function addExp(mixed $id, int $exp): array {
        $entity = $this->getEntity($id);
        // 共通ロジック
        $this->updateEntity($entity, $newLevel, $newExp);
        return $result;
    }
}

// Domain Layer (具体実装)
class UnitLevelService extends _BaseLevelService {
    protected function getEntity(mixed $id): object {
        return $this->trxUnitRepository->selectById($id);
    }
}
```

**注意:** 継承は強い結合を生むため、限定的に使用

## まとめ

### 推奨アクション

**優先度：高**
1. ✅ ItemService をパッケージ `nexus-resource` に移動
   - 再利用性が高い
   - ビジネスロジックが明確

**優先度：中**
2. InAppPurchaseService をパッケージ `nexus-core-billing` に移動
   - 課金処理は汎用的
   - 管理画面でも使用される可能性

**優先度：低**
3. GachaCostService / GachaValidationService を `nexus-gacha` に移動
4. VersionService を `nexus-version` のラッパーに変更

### 設計原則

1. **パッケージはHTTPに依存しない**
   - DTOを使用
   - Request/Responseに依存しない

2. **Domain層はラッパーに徹する**
   - DTO ↔ Model変換のみ
   - ビジネスロジックはパッケージに委譲

3. **UseCaseは変更しない**
   - Domain層のServiceを使い続ける
   - 内部実装のみ変更

4. **段階的に移行**
   - 一度にすべて移行しない
   - 優先度の高いものから順次移行

この方針により、**再利用可能**で**テスタブル**、かつ**保守性の高い**アーキテクチャを実現できます。
