# Naming Conventions

## Service命名規則（ハイブリッド方式）

### 原則: ドメイン名を含めない

Serviceクラスは**目的（Purpose）を表す名前**とし、ドメイン名は含めません。
UseCaseと同様の命名哲学を適用します。

**理由:**
- UseCaseとの一貫性を保つ
- ディレクトリ構造で既にドメインが明確
- 名前空間（namespace）でドメインが含まれる
- DRY原則（Don't Repeat Yourself）に準拠

**Good Examples:**
```php
// ✅ ドメイン名なし（目的が明確）
Domain/Gacha/Services/DrawService.php
Domain/Gacha/Services/CostService.php
Domain/Gacha/Services/PrizeService.php
Domain/Gacha/Services/ProgressService.php
Domain/Gacha/Services/ValidationService.php

Domain/InAppPurchase/Services/PurchaseService.php
Domain/InAppPurchase/Services/HistoryService.php

Domain/Item/Services/ReadService.php
Domain/Item/Services/WriteService.php

Domain/Wallet/Services/ReadService.php
Domain/Wallet/Services/WriteService.php
```

**Bad Examples:**
```php
// ❌ ドメイン名を含める（冗長）
Domain/Gacha/Services/GachaDrawService.php       // DrawService で十分
Domain/Gacha/Services/GachaCostService.php       // CostService で十分
Domain/InAppPurchase/Services/DiamondPurchaseService.php  // PurchaseService で十分
```

### 例外: 汎用的すぎる場合はドメイン名を許容

目的が`Service`や`Management`のような汎用的すぎる名前になる場合は、
**例外的に`{Domain}Service`を許容**します。

**Allowed Exceptions:**
```php
// ⚠️ 例外（汎用的すぎる名前）
Domain/Item/Services/ItemService.php       // ManagementService より自然
Domain/Wallet/Services/WalletService.php   // ManagementService より自然
Domain/Stamina/Services/StaminaService.php // ManagementService より自然
Domain/Player/Services/PlayerService.php   // ManagementService より自然
Domain/Version/Services/VersionService.php // CheckService より自然
Domain/Unit/Services/LevelService.php      // UnitLevelService は冗長
```

### UseCase命名規則（既存ルール）

UseCaseは**アクション（Action）を表す名前**とし、ドメイン名は含めません。

**Examples:**
```php
// ✅ ドメイン名なし
Domain/Gacha/UseCases/DrawUseCase.php           // GachaDrawUseCase ではない
Domain/InAppPurchase/UseCases/BuyDiamondUseCase.php  // InAppPurchaseBuyDiamondUseCase ではない
Domain/Auth/UseCases/SignInUseCase.php          // AuthSignInUseCase ではない
Domain/Friend/UseCases/ListUseCase.php          // FriendListUseCase ではない
```

## Repository命名規則（既存ルール）

### テーブル毎に個別Repository作成

集約Repositoryは使用せず、**テーブル毎に個別のRepository**を作成します。

### DB接続先プレフィックスを使用

Repository名には**DB接続先のプレフィックス**を含めます。

**Examples:**
```php
// マスターDB
MstGachaRepository
MstGachaPrizeRepository
MstItemRepository

// トランザクションDB
TrxPlayerRepository
TrxPlayerItemRepository
TrxPlayerGachaProgressRepository

// システムDB
SysPlayerTokenRepository
SysMaintenanceRepository
```

## Constants命名規則

### Package層 vs Application層

- **Package層**: フレームワークレベルの汎用定数（例外的）
- **Application層**: ゲーム固有のドメイン定数（推奨）

**ゲーム固有の定数はApplication層に配置:**
```php
// ✅ Application層（ゲーム固有）
App\Domain\Common\Constants\ElementType    // FIRE, WATER, WIND, EARTH, LIGHT, DARK
App\Domain\Common\Constants\RarityType     // UR, SSR, SR, R, UC, C
```

## 命名規則の優先順位

1. **明確性** - 名前だけで意図が伝わるか
2. **一貫性** - 既存のパターンと統一されているか
3. **簡潔性** - 冗長な情報を含んでいないか

## 新規作成時のチェックリスト

### Service作成時
- [ ] 目的が明確な名前か？（Draw, Cost, Purchase, Validationなど）
- [ ] ドメイン名を含めていないか？（例外を除く）
- [ ] UseCaseと同じ命名哲学に従っているか？
- [ ] 汎用的すぎる場合は例外ルールを適用したか？

### UseCase作成時
- [ ] アクションを表す名前か？（Draw, Buy, SignIn, Listなど）
- [ ] ドメイン名を含めていないか？

### Repository作成時
- [ ] テーブル毎に個別Repositoryになっているか？
- [ ] DB接続先プレフィックス（Mst/Trx/Sys）を含めているか？
- [ ] テーブル名と一致しているか？

## 参考資料

- [UseCaseパターン実装](../api/app/Domain/_BaseUseCase.php)
- [Repositoryパターン実装](../packages/nexus-core-persistence/)
- [過去のリファクタリング履歴](../CHANGELOG.md)
