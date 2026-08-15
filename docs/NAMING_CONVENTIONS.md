# Naming Conventions

## 全レイヤー共通ルール: ドメイン名を含める

すべてのクラス（Repository, Model, DTO, Controller, Service, UseCase）は**ドメイン名を含める**ことを原則とします。

**理由:**
- 全レイヤーで一貫した命名規則
- クラス名だけでドメインが明確（import文を見るだけで理解できる）
- 検索性向上（`GachaDrawService`で一意に特定可能）
- 名前の衝突を防ぐ（複数ドメインで同じ名前のServiceを避ける）

## Service命名規則

### 原則: ドメイン名を含める

Serviceクラスは**`{Domain}{Purpose}Service`**の形式とします。

**Good Examples:**
```php
// ✅ ドメイン名を含める
Domain/Gacha/Services/GachaDrawService.php
Domain/Gacha/Services/GachaCostService.php
Domain/Gacha/Services/GachaPrizeService.php
Domain/Gacha/Services/GachaProgressService.php
Domain/Gacha/Services/GachaValidationService.php

Domain/InAppPurchase/Services/InAppPurchasePurchaseService.php
Domain/InAppPurchase/Services/InAppPurchaseHistoryService.php

Domain/Item/Services/ItemReadService.php
Domain/Item/Services/ItemWriteService.php

Domain/Wallet/Services/WalletReadService.php
Domain/Wallet/Services/WalletWriteService.php
```

**Bad Examples:**
```php
// ❌ ドメイン名なし（検索性・一意性に問題）
Domain/Gacha/Services/DrawService.php       // GachaDrawService にすべき
Domain/Gacha/Services/CostService.php       // GachaCostService にすべき
Domain/InAppPurchase/Services/PurchaseService.php  // InAppPurchasePurchaseService にすべき
```

### Package層も同様

Package層のServiceも同じルールを適用します。

**Examples:**
```php
// ✅ Package層もドメイン名を含める
packages/nexus-gacha/Services/GachaDrawService.php
packages/nexus-gacha/Services/GachaPrizeService.php
packages/nexus-vip/Services/VipLevelService.php
packages/nexus-player/Services/PlayerLevelService.php
packages/nexus-friend/Services/FriendService.php
```

## UseCase命名規則

### 原則: ドメイン名を含める

UseCaseは**`{Domain}{Action}UseCase`**の形式とします。

**Examples:**
```php
// ✅ ドメイン名を含める
Domain/Gacha/UseCases/GachaDrawUseCase.php
Domain/InAppPurchase/UseCases/InAppPurchaseBuyDiamondUseCase.php
Domain/Auth/UseCases/AuthSignInUseCase.php
Domain/Friend/UseCases/FriendListUseCase.php
Domain/Friend/UseCases/FriendApplySendUseCase.php
Domain/Mailbox/UseCases/MailboxListUseCase.php
Domain/Mailbox/UseCases/MailboxReceiveAllUseCase.php
```

**Bad Examples:**
```php
// ❌ ドメイン名なし
Domain/Gacha/UseCases/DrawUseCase.php           // GachaDrawUseCase にすべき
Domain/Friend/UseCases/ListUseCase.php          // FriendListUseCase にすべき
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

## Model命名規則

Modelは**テーブル名に対応**し、DB接続先プレフィックスを含めます。

**Examples:**
```php
// マスターDB
MstGacha
MstGachaPrize
MstItem

// トランザクションDB
TrxPlayer
TrxPlayerItem

// システムDB
SysPlayerToken
SysMaintenance
```

## DTO命名規則

DTOは**ドメイン名 + 目的 + Dto**の形式とします。

**Examples:**
```php
GachaPrize
GachaProgress
Player
FriendApply
Friend
```

## Controller命名規則

Controllerは**ドメイン名 + Controller**の形式とします。

**Examples:**
```php
GachaController
FriendController
AuthController
PlayerController
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

**Package層の定数:**
```php
// ✅ Package層（フレームワーク汎用）
NexusFriend\Constants\FriendStatus    // APPLIED, ACCEPTED, REJECTED, DELETED
```

## 命名規則の優先順位

1. **統一性** - 全レイヤーで同じルールを適用
2. **明確性** - 名前だけで意図が伝わるか
3. **一貫性** - 既存のパターンと統一されているか
4. **検索性** - クラス名で一意に特定できるか

## 新規作成時のチェックリスト

### Service作成時
- [ ] ドメイン名を含めているか？（`{Domain}{Purpose}Service`）
- [ ] 目的が明確な名前か？（Draw, Cost, Purchase, Validationなど）

### UseCase作成時
- [ ] ドメイン名を含めているか？（`{Domain}{Action}UseCase`）
- [ ] アクションを表す名前か？（Draw, Buy, SignIn, Listなど）

### Repository作成時
- [ ] テーブル毎に個別Repositoryになっているか？
- [ ] DB接続先プレフィックス（Mst/Trx/Sys）を含めているか？
- [ ] テーブル名と一致しているか？

### Model作成時
- [ ] テーブル名に対応しているか？
- [ ] DB接続先プレフィックスを含めているか？

### DTO作成時
- [ ] ドメイン名を含めているか？
- [ ] `Dto`サフィックスがついているか？

### Controller作成時
- [ ] ドメイン名を含めているか？
- [ ] `Controller`サフィックスがついているか？

## 参考資料

- [UseCaseパターン実装](../api/app/Domain/_BaseUseCase.php)
- [Repositoryパターン実装](../packages/nexus-core-persistence/)
- [過去のリファクタリング履歴](../CHANGELOG.md)
