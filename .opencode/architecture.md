# アーキテクチャ設計 / Architecture Design

このドキュメントでは、プロジェクトのアーキテクチャ設計、レイヤー構造、依存関係のルールについて説明します。

## 目次

- [レイヤードアーキテクチャ](#レイヤードアーキテクチャ)
- [依存関係のルール](#依存関係のルール)
- [ディレクトリ構造](#ディレクトリ構造)
- [各層の責務](#各層の責務)

---

## レイヤードアーキテクチャ

プロジェクトは以下の**4層構造**で構成されます：

```
┌─────────────────────────────────────┐
│  Presentation Layer (Controller)    │ ← HTTPリクエスト/レスポンス
├─────────────────────────────────────┤
│  Application Layer (UseCase)        │ ← ビジネスフロー制御
├─────────────────────────────────────┤
│  Domain Layer (Service/Repository)  │ ← ビジネスロジック
├─────────────────────────────────────┤
│  Infrastructure Layer (Model)       │ ← データアクセス
└─────────────────────────────────────┘
```

### 各層の役割

| 層 | コンポーネント | 役割 |
|---|---------------|-----|
| **Presentation Layer** | Controller, Request, Response | HTTPリクエストの受付とレスポンス生成 |
| **Application Layer** | UseCase | ビジネスフローの制御とトランザクション管理 |
| **Domain Layer** | Service, Repository, Handler, DTO | ビジネスロジックの実装 |
| **Infrastructure Layer** | Model | データベースアクセス |

---

## 依存関係のルール

### 基本原則

- **上位層は下位層に依存できる**が、**下位層は上位層に依存してはいけない**
- Controller → UseCase → Service → Model の一方向の依存
- 各層は自分の責務のみに集中する（単一責任の原則）

### 依存関係の図

```
Controller
   ↓
UseCase
   ↓
Service ← → Repository
   ↓           ↓
Model ← ─ ─ ─ ┘
```

### 許可される依存

```php
// ✅ Good: 上位層から下位層への依存
class AuthController extends Controller
{
    public function version(
        VersionCheckRequest $request,    // ← Presentation Layer
        VersionCheckUseCase $useCase      // ← Application Layer
    ): JsonResponse {
        // Requestから値を取り出してUseCaseに渡す
        $deployVersion = $request->getDeployVersion();
        $response = $useCase->handle($deployVersion);
        return $response->toJsonResponse();
    }
}

// ✅ Good: UseCaseからServiceへの依存
class VersionCheckUseCase
{
    public function __construct(
        private readonly VersionCheckService $versionCheckService
    ) {}
    
    public function handle(?int $deployVersion): VersionCheckResponse
    {
        return $this->versionCheckService->checkVersion($deployVersion);
    }
}

// ✅ Good: ServiceからModelへの依存
class VersionCheckService
{
    public function checkVersion(?int $currentDeployId): VersionCheckResponse
    {
        $latestDeploy = SysDeploy::getLatestDownloadable();
        // ...
    }
}
```

### 禁止される依存

```php
// ❌ Bad: ModelからServiceへの依存
class SysDeploy extends Model
{
    public function updateVersion(): void
    {
        $service = new VersionCheckService();  // NG: Modelが上位層に依存
        $service->update($this);
    }
}

// ❌ Bad: ServiceからControllerへの依存
class VersionCheckService
{
    public function checkVersion(AuthController $controller): void  // NG
    {
        // ...
    }
}
```

---

## ディレクトリ構造

### 全体構成

```
api/
├── app/
│   ├── Domain/                    # ドメイン層（ビジネスロジック）
│   │   └── {Domain}/
│   │       ├── DTOs/              # DTOクラス（データ構造定義、サフィックスなし）
│   │       ├── Services/          # ビジネスロジック実装
│   │       ├── UseCases/          # アプリケーションロジック
│   │       ├── Handlers/          # Strategy Pattern実装
│   │       └── Constants/         # 定数クラス（Constサフィックス）
│   │
│   ├── Http/
│   │   ├── Controllers/           # プレゼンテーション層（薄く保つ）
│   │   ├── Requests/              # バリデーションロジック
│   │   └── Responses/             # レスポンスDTO（Responseサフィックス）
│   │
│   ├── Models/                    # Eloquentモデル（データアクセス層）
│   │   ├── Adm/                   # 管理DB
│   │   ├── Tol/                   # ツールDB
│   │   ├── Sys/                   # システムDB
│   │   ├── Mst/                   # マスターDB
│   │   ├── Log/                   # ログDB
│   │   ├── Trx1/                  # トランザクションDB1
│   │   └── Trx2/                  # トランザクションDB2
│   │
│   ├── Repositories/              # リポジトリ（データアクセス抽象化）
│   │   ├── Sys/                   # システムDB用Repository
│   │   ├── Mst/                   # マスターDB用Repository
│   │   └── Trx/                   # トランザクションDB用Repository
│   │
│   └── Utilities/                 # ユーティリティクラス（汎用ヘルパー）
│
├── database/
│   └── migrations/
│       ├── adm/                   # 各データベース別にマイグレーション管理
│       ├── tol/
│       ├── sys/
│       ├── mst/
│       ├── log/
│       ├── trx1/
│       └── trx2/
│
└── routes/
    └── api.php                    # APIルート定義
```

### Domainディレクトリの構造

各ドメイン（例: Auth, Delivery, Wallet等）は以下の構造を持ちます：

```
app/Domain/{Domain}/
├── DTOs/                          # データ転送オブジェクト
│   ├── {Name}.php                 # サフィックスなし（例: DeliveryContent）
│   └── {Name}Result.php
│
├── Services/                      # ビジネスロジック
│   └── {Name}Service.php          # Serviceサフィックス
│
├── UseCases/                      # ユースケース
│   └── {Name}UseCase.php          # UseCaseサフィックス
│
├── Handlers/                      # Strategy Pattern
│   ├── {Name}HandlerInterface.php # Interfaceサフィックス
│   ├── {Type1}Handler.php         # Handlerサフィックス
│   └── {Type2}Handler.php
│
└── Constants/                     # 定数
    └── {Name}Const.php            # Constサフィックス
```

**例: Deliveryドメイン**

```
app/Domain/Delivery/
├── DTOs/
│   ├── DeliveryContent.php        # ← サフィックスなし
│   └── DeliveryResult.php
├── Services/
│   └── DeliveryService.php
├── UseCases/
│   └── DeliveryUseCase.php
├── Handlers/
│   ├── DeliveryHandlerInterface.php
│   ├── ItemDeliveryHandler.php
│   ├── UnitDeliveryHandler.php
│   └── DiamondDeliveryHandler.php
└── Constants/
    └── DeliveryConst.php
```

---

### Package層のディレクトリ構造

パッケージ（例: nexus-vip, nexus-gacha等）は以下の構造を持ちます：

```
packages/{package-name}/
├── src/
│   ├── DTOs/                          # データ転送オブジェクト
│   │   ├── {Name}Dto.php              # Dtoサフィックス（例: PlayerVipDto）
│   │   └── {Name}ResultDto.php
│   │
│   ├── ValueObjects/                  # Value Object（不変オブジェクト）
│   │   └── {Name}.php                 # サフィックスなし（例: VipConfig）
│   │
│   ├── Services/                      # ビジネスロジック
│   │   └── {Name}Service.php          # Serviceサフィックス
│   │
│   ├── Repositories/                  # Repositoryインターフェース
│   │   └── {Name}RepositoryInterface.php
│   │
│   ├── Models/                        # Eloquentモデル（パッケージ専用）
│   │   └── {Name}.php
│   │
│   ├── Events/                        # イベント
│   │   └── {Name}Event.php
│   │
│   ├── Exceptions/                    # 例外クラス
│   │   └── {Name}Exception.php
│   │
│   └── {Package}ServiceProvider.php   # サービスプロバイダー
│
├── tests/
│   └── Unit/
│       ├── DTOs/
│       ├── Services/
│       └── ValueObjects/
│
├── config/
│   └── {package}.php                  # 設定ファイル
│
└── composer.json
```

**例: nexus-vipパッケージ**

```
packages/nexus-vip/
├── src/
│   ├── DTOs/
│   │   ├── PlayerVipDto.php           # データ転送（Repository ↔ Service）
│   │   ├── VipBenefitDto.php
│   │   └── VipLevelDto.php
│   │
│   ├── ValueObjects/
│   │   └── VipConfig.php              # 設定のValue Object（完全不変）
│   │
│   ├── Services/
│   │   ├── VipPointService.php
│   │   ├── VipLevelService.php
│   │   └── VipBenefitService.php
│   │
│   ├── Repositories/
│   │   ├── PlayerVipRepositoryInterface.php
│   │   └── VipLevelRepositoryInterface.php
│   │
│   ├── Models/
│   │   └── MstVipLevel.php
│   │
│   ├── Events/
│   │   └── VipLevelUpEvent.php
│   │
│   ├── Exceptions/
│   │   └── InvalidVipPointException.php
│   │
│   └── VipServiceProvider.php
│
├── tests/
│   └── Unit/
│       ├── DTOs/
│       │   └── PlayerVipDtoTest.php
│       └── Services/
│           └── VipPointServiceTest.php
│
├── config/
│   └── vip.php
│
└── composer.json
```

**DTOs/ と ValueObjects/ の使い分け:**

- **DTOs/**: データ転送用（可変も許容、toArray()あり）
  - 例: `PlayerVipDto`, `GuildDto`, `DeliveryResultDto`

- **ValueObjects/**: ドメイン概念を表現（完全不変、toArray()なし）
  - 例: `VipConfig`, `Money`, `DateRange`, `GameSettings`

詳細は [DTO vs Value Object の使い分け](#dto-vs-value-object-の使い分け) を参照してください。

---

## 各層の責務

### Presentation Layer（プレゼンテーション層）

**責務:**
- HTTPリクエストの受付
- バリデーション（FormRequest）
- UseCaseの呼び出し
- HTTPレスポンスの生成

**コンポーネント:**
- **Controller**: リクエストをUseCaseに委譲（10行以内を目安）
- **Request**: バリデーションルールの定義
- **Response**: レスポンス構造の定義

**実装例:**

```php
// Controller
class AuthController extends Controller
{
    public function version(
        VersionCheckRequest $request,
        VersionCheckUseCase $useCase
    ): JsonResponse {
        // Requestから個々の値を取り出してUseCaseに渡す
        $deployVersion = $request->getDeployVersion();
        $response = $useCase->handle($deployVersion);
        return $response->toJsonResponse();
    }
}

// Request
class VersionCheckRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'deploy_version' => 'nullable|integer',
        ];
    }
    
    public function getDeployVersion(): ?int
    {
        return $this->input('deploy_version');
    }
}

// Response
class VersionCheckResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly ?int $latestDeployId = null,
    ) {}
}
```

### Application Layer（アプリケーション層）

**責務:**
- ビジネスフローの制御
- 複数のServiceの組み合わせ
- トランザクション管理（Unit of Work パターン）
- ユースケース単位の処理
- **Responseオブジェクトの合成**（重要）

**コンポーネント:**
- **UseCase**: 1つのユースケースを表現

#### 重要なルール: Responseの合成はUseCaseの責務

**ServiceはResponseを返してはいけません。Serviceはビジネスロジックとデータのみを扱い、UseCaseがResponseを合成します。**

**理由:**
1. **責務の分離**: ServiceはHTTP層（Presentation Layer）から独立すべき
2. **再利用性**: Serviceを別のコンテキスト（CLI、Queue等）から呼び出せる
3. **テスタビリティ**: Serviceのテストでレスポンス構造を気にする必要がない
4. **アーキテクチャの整合性**: 下位層（Service）が上位層（Response）に依存しない

**❌ Bad: ServiceがResponseを返す（アーキテクチャ違反）**

```php
// Service（Domain Layer）
class VersionCheckService
{
    public function checkVersion(?int $currentDeployId): VersionResponse  // NG: ResponseはPresentation層
    {
        $latestDeploy = $this->sysDeployRepository->selectLatestDownloadable();
        // ...
        
        // NG: ServiceでResponseを合成
        return VersionResponse::updateAvailable(
            latestDeployId: $latestDeploy->id,
            latestDeployKey: $latestDeploy->deploy_key,
            dtoMaster: $dtoMaster,
            dtoAsset: $dtoAsset,
            dtoMaintenance: $dtoMaintenance,
        );
    }
}

// UseCase（Application Layer）
class VersionUseCase
{
    public function handle(?int $deployVersion): VersionResponse
    {
        // NG: UseCaseが単なるパススルーになっている
        return $this->versionCheckService->checkVersion($deployVersion);
    }
}
```

**✅ Good: ServiceはデータのみをDTOで返し、UseCaseがResponseを合成**

```php
// DTO（Domain Layer）
readonly class VersionCheckResult
{
    public function __construct(
        public bool $needsUpdate,
        public ?SysDeploy $sysDeploy = null,           // Eloquentモデルを直接使用
        public ?SysMaintenance $sysMaintenance = null,  // Eloquentモデルを直接使用
    ) {
    }
}

// Service（Domain Layer）
class VersionCheckService
{
    /**
     * バージョンチェックを実行
     * 
     * @return VersionCheckResult
     */
    public function checkVersion(?int $currentDeployId): VersionCheckResult  // OK: DTOを返す
    {
        $sysMaintenance = $this->sysMaintenanceRepository->selectCurrentMaintenance();
        $latestDeploy = $this->sysDeployRepository->selectLatestDownloadable();
        
        // DTOを返す（Responseは返さない）
        if ($latestDeploy === null || $currentDeployId === $latestDeploy->id) {
            return new VersionCheckResult(
                needsUpdate: false,
                sysMaintenance: $sysMaintenance,
            );
        }
        
        // リレーションを読み込む
        $latestDeploy->load(['deployMaster', 'deployAsset']);
        
        // 更新が必要な場合もDTOを返す（Eloquentモデルをそのまま渡す）
        return new VersionCheckResult(
            needsUpdate: true,
            sysDeploy: $latestDeploy,
            sysMaintenance: $sysMaintenance,
        );
    }
}

// UseCase（Application Layer）
class VersionUseCase
{
    public function handle(?int $deployVersion): VersionResponse
    {
        // 1. Serviceからデータ（DTO）を取得
        $result = $this->versionCheckService->checkVersion($deployVersion);
        
        // 2. UseCaseでResponseを合成（この層の責務）
        if (!$result->needsUpdate) {
            return VersionResponse::upToDate($result->sysMaintenance);
        }
        
        return VersionResponse::updateAvailable(
            sysDeploy: $result->sysDeploy,
            sysMaintenance: $result->sysMaintenance
        );
    }
}
```

**Serviceの戻り値の選択肢:**
- **DTO**: 構造化されたデータオブジェクト（推奨）
- **Eloquentモデル**: データベースのレコード（リレーション含む）
- **配列**: 複数のDTO/モデルの組み合わせ
- **プリミティブ**: int, bool, string等の単純な値

**重要: DTOを作るべきか、Eloquentモデルを使うべきか**

既存のEloquentモデルで十分な場合は、**新たにDTOを作る必要はありません**。

**✅ Good: Eloquentモデルをそのまま返す（DTOは不要）**
```php
// Service
class VersionCheckService
{
    public function checkVersion(?int $currentDeployId): VersionCheckResult
    {
        $latestDeploy = $this->sysDeployRepository->selectLatestDownloadable();
        $latestDeploy->load(['deployMaster', 'deployAsset']);  // リレーション込み
        
        // EloquentモデルをそのままDTOのプロパティとして渡す
        return new VersionCheckResult(
            needsUpdate: true,
            sysDeploy: $latestDeploy,  // SysDeployモデル（リレーション込み）
            sysMaintenance: $sysMaintenance,  // SysMaintenanceモデル
        );
    }
}
```

**❌ Bad: 不要なDTOを作成（冗長）**
```php
// 不要なDTO（SysDeployMasterの情報だけを持つ）
readonly class DtoMaster
{
    public function __construct(
        public int $deployMasterId,
        public string $hash,
    ) {}
}

// Service
class VersionCheckService
{
    public function checkVersion(?int $currentDeployId): VersionCheckResult
    {
        $latestDeploy = $this->sysDeployRepository->selectLatestDownloadable();
        
        // わざわざDTOに詰め直す必要はない
        $dtoMaster = new DtoMaster(
            deployMasterId: $latestDeploy->sys_deploy_master_id,
            hash: $latestDeploy->deployMaster->hash,
        );
        
        return new VersionCheckResult(..., dtoMaster: $dtoMaster);
    }
}
```

**DTOを作るべきケース:**
- Eloquentモデルに存在しない計算結果や加工データを含む場合
- 複数のモデルを組み合わせた新しいデータ構造が必要な場合
- API仕様上、特定のフォーマットでデータを返す必要がある場合

**Eloquentモデルを使うべきケース:**
- モデルのプロパティやリレーションがそのまま使える場合
- 追加の加工が不要な場合
- シンプルに保ちたい場合

---

### DTO vs Value Object の使い分け

**DTO (Data Transfer Object)** と **Value Object** は異なる目的と特性を持ちます。

#### DTO (Data Transfer Object)

**目的**: レイヤー間・システム間でデータを転送する

**特徴**:
- データの「運搬役」
- 構造は転送要件に依存
- getter/setterを持つことがある（可変も許容）
- `toArray()` メソッドを持つ（DB/APIとの変換用）
- バリデーションロジックは最小限

**配置**: `src/DTOs/` または `app/Domain/{Domain}/DTOs/`

**実装例（nexus-vip）:**

```php
namespace NexusVip\DTOs;

/**
 * プレイヤーVIP情報DTO
 * Repository ↔ Service 間でデータを転送
 */
class PlayerVipDto
{
    public function __construct(
        private readonly int $sysPlayerId,
        private int $vipPoint,              // 可変（setterあり）
        private float $totalPaidAmount,     // 可変（setterあり）
    ) {}
    
    // getter
    public function getVipPoint(): int
    {
        return $this->vipPoint;
    }
    
    // setter（転送後に値を変更可能）
    public function addVipPoint(int $points): void
    {
        $this->vipPoint += $points;
    }
    
    // 配列変換（DB/API用）
    public function toArray(): array
    {
        return [
            'sys_player_id' => $this->sysPlayerId,
            'vip_point' => $this->vipPoint,
            'total_paid_amount' => $this->totalPaidAmount,
        ];
    }
}
```

**使用例:**
```php
// Repository → Service
$playerVip = $this->playerVipRepository->findVipInfoById($playerId);  // PlayerVipDto
$playerVip->addVipPoint(100);  // DTO内で値を変更
$this->playerVipRepository->save($playerVip);
```

---

#### Value Object

**目的**: ドメイン概念を表現する不変の値

**特徴**:
- ビジネスロジックの一部
- 概念的な「値」を表現（金額、期間、設定など）
- **完全に不変（immutable）** - すべてのフィールドが `readonly`
- setter なし
- `toArray()` なし（転送が目的ではない）
- 等価性は値で判断（同じ値 = 同じオブジェクト）
- ビジネスルールを内包できる

**配置**: `src/ValueObjects/` または `app/Domain/{Domain}/ValueObjects/`

**実装例（nexus-vip）:**

```php
namespace NexusVip\ValueObjects;

/**
 * VIP設定 Value Object
 * VIPシステムの設定という「概念」を表現
 */
class VipConfig
{
    public function __construct(
        public readonly bool $enablePointLog = true,
        public readonly bool $enableLevelUpEvent = true,
        public readonly bool $staminaBonusEnabled = true,
        public readonly bool $shopDiscountEnabled = true,
        public readonly bool $gachaDiscountEnabled = true,
        public readonly bool $dailyDiamondEnabled = true,
    ) {}
    
    // getter も不要（public readonly で直接アクセス可能）
    // setter は存在しない（完全不変）
    // toArray() なし（転送目的ではない）
}
```

**使用例:**
```php
// Application層（ServiceProvider）で生成
$this->app->singleton(VipConfig::class, function () {
    return new VipConfig(
        enablePointLog: config('vip.enable_point_log', true),
        enableLevelUpEvent: config('vip.enable_level_up_event', true),
        // ...
    );
});

// Package層（Service）で使用
class VipPointService
{
    public function __construct(
        protected VipConfig $config,  // DI
    ) {}
    
    public function addPoints(int $points): PlayerVipDto
    {
        // 設定値を参照（変更不可）
        if ($this->config->enablePointLog) {
            $this->logVipPointChange(...);
        }
        // ...
    }
}

// テストでの使用
$config = new VipConfig(
    enablePointLog: false,  // テスト用設定
    enableLevelUpEvent: false,
);
$service = new VipPointService($config);
```

---

#### 比較表

| 観点 | **DTO** | **Value Object** |
|------|---------|------------------|
| **目的** | データ転送 | ドメイン概念の表現 |
| **可変性** | 可変も許容（setterあり） | 完全不変（readonly のみ） |
| **toArray()** | ✅ あり（DB/API用） | ❌ なし（転送目的でない） |
| **ビジネスロジック** | 最小限 | 含むことができる |
| **等価性判断** | IDベース | 値ベース |
| **配置** | `DTOs/` | `ValueObjects/` |
| **使用箇所** | Repository ↔ Service | Service内のロジック |
| **例** | PlayerVipDto, GuildDto | VipConfig, Money, Period |

---

#### どちらを使うべきか？

**DTOを使うケース:**
- ✅ Repository と Service 間でデータを運ぶ
- ✅ 外部API とのデータ交換
- ✅ 複数のモデルを組み合わせた構造
- ✅ データベースとの変換が必要（toArray/fromArray）

**Value Objectを使うケース:**
- ✅ 設定値を表現（VipConfig, GameConfig）
- ✅ 金額・期間などのドメイン概念（Money, DateRange）
- ✅ 完全に不変であるべきデータ
- ✅ ビジネスルールを内包する値（Email, PhoneNumber）

**実装例:**

```php
class VersionCheckUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly VersionCheckService $versionCheckService
    ) {}

    public function handle(?int $deployVersion): VersionCheckResponse
    {
        // Serviceからデータを取得
        $versionData = $this->versionCheckService->checkVersion($deployVersion);
        
        // UseCaseでResponseを合成
        if (!$versionData['needsUpdate']) {
            return VersionCheckResponse::upToDate($versionData['dtoMaintenance']);
        }
        
        return VersionCheckResponse::updateAvailable(
            latestDeployId: $versionData['latestDeployId'],
            latestDeployKey: $versionData['latestDeployKey'],
            dtoMaster: $versionData['dtoMaster'],
            dtoAsset: $versionData['dtoAsset'],
            dtoMaintenance: $versionData['dtoMaintenance']
        );
    }
}
```

#### validation()メソッドの規約

**すべてのUseCaseは、handle()実行前にvalidation()メソッドを実装することを推奨します。**

**目的:**
- ビジネスロジック実行前の事前チェック
- トランザクション開始前のバリデーション（不要なトランザクションを避ける）
- エラーの早期発見

**実装規約:**
1. **メソッド名**: `validation()`
2. **可視性**: `public` または `private`（UseCaseの設計による）
3. **戻り値**: `void`
4. **引数**: handle()と同じパラメータ、または必要なパラメータのみ
5. **型安全性**: 各UseCaseで適切な型定義を行う（`mixed ...$args`は使用しない）
6. **例外**: バリデーションエラー時は適切な例外をthrow

**実装例:**

```php
class UnitLevelUpUseCase implements _BaseUseCaseInterface
{
    /**
     * バリデーション
     * 
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @param int $trxUnitId trx_unit.id（プレイヤー所有ユニット）
     * @param string $mstItemId mst_item.id（マスター定義アイテム）
     * @param int $useCount 使用個数
     * @return void
     * @throws TransactionDataException ユニットが存在しない場合
     * @throws MasterDataException アイテムマスターが存在しない場合
     * @throws BusinessLogicException アイテムタイプが不正、または所持数が不足
     */
    public function validation(int $sysPlayerId, int $trxUnitId, string $mstItemId, int $useCount): void
    {
        // 1. ユニットの存在確認
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        if (!$trxUnit) {
            throw TransactionDataException::unit($trxUnitId);
        }

        // 2. プレイヤー所有確認
        if ($trxUnit->sys_player_id !== $sysPlayerId) {
            throw new GameException(
                GameErrorCode::INVALID_PARAMETER,
                "Unit does not belong to player"
            );
        }

        // 3. アイテムマスターデータの存在確認
        $mstItem = $this->mstItemRepository->selectById($mstItemId);
        if (!$mstItem) {
            throw MasterDataException::item($mstItemId);
        }

        // 4. アイテムタイプチェック
        if ($mstItem->type !== 'UnitEnhancement' || $mstItem->effect !== 'UnitExp') {
            throw BusinessLogicException::invalidItemType(
                $mstItemId,
                'UnitEnhancement/UnitExp',
                "{$mstItem->type}/{$mstItem->effect}"
            );
        }

        // 5. アイテム所持数確認
        $currentAmount = $this->trxItemRepository->getItemAmount($sysPlayerId, $mstItemId);
        if ($currentAmount < $useCount) {
            throw BusinessLogicException::itemNotEnough($mstItemId, $useCount, $currentAmount);
        }
    }

    /**
     * ユニット経験値アイテムを使用してレベルアップ
     */
    public function handle(int $sysPlayerId, int $trxUnitId, string $mstItemId, int $useCount): array
    {
        // バリデーション実行
        $this->validation($sysPlayerId, $trxUnitId, $mstItemId, $useCount);

        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxUnitId, $mstItemId, $useCount) {
            // ビジネスロジック実行
            // ...
        });
    }
}
```

**バリデーション不要な場合:**

シンプルな読み取り専用のUseCaseでは、validation()を省略しても構いません。

```php
class VersionCheckUseCase implements _BaseUseCaseInterface
{
    // バリデーション不要（deployVersionはRequestで検証済み）
    public function handle(?int $deployVersion): VersionCheckResponse
    {
        return $this->versionCheckService->checkVersion($deployVersion);
    }
}
```

**設計の利点:**
- **型安全性**: 各UseCaseで適切な型定義が可能
- **柔軟性**: バリデーション不要なUseCaseで無駄な実装が不要
- **可読性**: validation()とhandle()の責務が明確に分離
- **テスタビリティ**: validation()を個別にテスト可能

#### トランザクション管理（Unit of Work パターン）

このプロジェクトでは、**Unit of Work パターン**を採用しています。

**重要な設計思想:**
- UseCaseのコールバック内では**SELECT（読み取り）のみ**を実行
- INSERT/UPDATE/DELETE は`TrxQueryManager`/`LogQueryManager`/`SysQueryManager`に**キューイング**される
- トランザクション開始**後**に、キューイングされたクエリを**一括実行**

**実装例（UseCaseTrait）:**

```php
use UseCaseTrait;

public function handle(int $unitId, int $itemId, int $amount): Response
{
    return $this->executeWithTransaction(function() use ($unitId, $itemId, $amount) {
        // 1. この時点ではまだトランザクション開始していない
        // 2. ここでは SELECT のみ実行（データの読み取り）
        $unit = TrxUnit::find($unitId);
        
        // 3. Repository経由の save() は QueryManager にキューイングされる
        $this->trxItemRepository->consumeItem(...);  // ← キューイング
        $this->trxUnitRepository->setModel($unit);   // ← キューイング
        
        return $result;  // ← この return の後にトランザクション開始
    });
    // 4. トランザクション開始
    // 5. QueryManager.execAllQuery() でクエリ一括実行
    // 6. コミット
}
```

**UseCaseTraitの処理フロー:**

```
1. コールバック実行（SELECT のみ）
   ├── データの読み取り
   └── 変更内容を QueryManager にキューイング
   
2. トランザクション開始
   ├── DB::connection('sys')->beginTransaction()
   ├── DB::connection('trx')->beginTransaction()
   └── DB::connection('log')->beginTransaction()
   
3. キューイングされたクエリを一括実行
   ├── QueryManager::execPurchaseQuery()  ← 課金ログを先に実行
   └── QueryManager::execAllQuery()       ← Sys/Trx/Log一括実行
       ├── Sys: sys_player, sys_player_device, sys_player_tokenのみ個別INSERT
       ├── Sys: その他のテーブルはバッチINSERT
       ├── Trx: バッチINSERT/UPDATE/DELETE
       └── Log: バッチINSERT
   
4. トランザクションコミット
   ├── DB::connection('sys')->commit()
   ├── DB::connection('trx')->commit()
   └── DB::connection('log')->commit()
```

**設計の利点:**
- すべての変更が一箇所で実行されるため、デバッグしやすい
- N+1問題を回避できる（バルクINSERT/UPDATE）
- トランザクション開始前にビジネスロジックを検証できる
- 複数のRepositoryの変更を一括してコミット/ロールバック可能

**注意事項:**
- コールバック内でINSERT/UPDATE/DELETEを直接実行してはいけない
- 必ずRepository経由でQueryManagerにキューイングすること
- トランザクション内で実行したい処理は必ず`executeWithTransaction()`メソッドでラップする

### Domain Layer（ドメイン層）

**責務:**
- ビジネスロジックの実装
- ドメイン知識のカプセル化
- データアクセスの抽象化（Repository）
- 戦略パターン（Handler）

**コンポーネント:**
- **Service**: ビジネスロジックの実装
- **Repository**: データアクセスの抽象化とキャッシュ管理
- **Handler**: Strategy Patternの実装
- **DTO**: データ構造の定義
- **Constants**: 定数の定義

**Service層の実装規約:**

#### 1. Repository経由のDB操作（重要）

**Service層では、直接Eloquentの`save()`, `update()`, `delete()`を呼び出してはいけません。**

必ず**Repository経由**でDB操作を行うこと。

**理由:**
1. **QueryManagerのキューイング機能を活用** - トランザクション管理が正しく機能
2. **ログ記録の自動化** - Repositoryのフック処理が実行される
3. **updated_atの自動設定** - `_BaseTrxRepository.setModel()`内で自動的に`updated_at`が設定される
4. **テスト容易性** - Repositoryをモックできる
5. **責務の分離** - ServiceがDB操作の詳細を知らない

**❌ Bad: Service内で直接save()を呼び出す**

```php
class UnitLevelService
{
    public function addExp(int $trxUnitId, int $exp): array
    {
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        $trxUnit->level = 10;
        $trxUnit->save();  // NG: QueryManagerをバイパス、ログ記録漏れ、テスト困難
        
        return ['level' => $trxUnit->level];
    }
}
```

**✅ Good: Repository経由でDB操作（setModel()を直接使用）**

```php
class UnitLevelService
{
    public function addExp(int $trxUnitId, int $exp): array
    {
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        $trxUnit->level = 10;
        $this->trxUnitRepository->setModel($trxUnit);  // OK: QueryManagerにキューイング、updated_at自動設定、ログ自動記録
        
        return ['level' => $trxUnit->level];
    }
}
```

**Trx Repositoryの`setModel()`について:**

`_BaseTrxRepository`の`setModel()`メソッドは、以下の処理を自動的に行います：

1. **`updated_at`の自動設定** - `ClockUtility::now()`で現在時刻を設定
2. **QueryManagerへのキューイング** - トランザクション内で一括実行
3. **ログ記録** - 変更履歴を自動的に記録

```php
// _BaseTrxRepository.php
abstract class _BaseTrxRepository extends _BaseRepository
{
    protected function setModel($model): void
    {
        // updated_atを自動設定
        $model->updated_at = ClockUtility::now();

        // ユニークキーを生成
        $uniqueKey = implode(':', array_map(fn($key) => $model->getAttribute($key), $this->getUniqueKeys()));

        // CacheRecordTraitのキャッシュに保存
        if ($this->models === null) {
            $this->models = collect();
        }
        $this->models->put($uniqueKey, $model);

        // 内部キューに溜め込む（同じキーは上書き = 最終状態を保持）
        $this->modelQueue[$uniqueKey] = $model;

        // QueryManagerに自身を登録（初回のみ）
        if (!$this->registeredToManager) {
            $queryManager = app()->make(\App\Repositories\QueryManager::class);
            $queryManager->registerRepository($this);
            $this->registeredToManager = true;
        }
    }
}
```

**Sys Repositoryの場合:**

Sysデータベースのモデルは、キャッシュクリアが必要なため、専用のメソッドを用意します：

```php
// SysPlayerRepository.php
class SysPlayerRepository extends _BaseSysRepository
{
    /**
     * Playerを更新
     * 
     * @param SysPlayer $player
     * @return bool
     */
    public function updatePlayer(SysPlayer $player): bool
    {
        $result = $player->save();
        $this->clearCache($player->id);  // キャッシュクリア
        return $result;
    }
}
```

**実装例:**

```php
// WalletService.php（Trxデータベース）
class WalletService
{
    public function addCurrency(int $sysPlayerId, string $mstItemId, int $amount): array
    {
        // 1. 現在値を取得
        $wallet = TrxWallet::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        if ($wallet === null) {
            $wallet = new TrxWallet([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'amount' => 0,
            ]);
        }

        // 2. 通貨加算
        $wallet->amount += $amount;
        $this->walletRepository->setModel($wallet);  // ✅ updated_at自動設定、キューイング
        
        return ['total_amount' => $wallet->amount];
    }
}

// PlayerLevelService.php（Sysデータベース）
class PlayerLevelService
{
    public function addExp(int $sysPlayerId, int $exp): array
    {
        $player = SysPlayer::find($sysPlayerId);
        
        // レベルアップ処理...
        $player->level = $afterLevel;
        $player->level_exp = $newTotalExp;
        $this->sysPlayerRepository->updatePlayer($player);  // ✅ save() + キャッシュクリア
        
        return ['after_level' => $afterLevel];
    }
}
```
        
        // ビジネスロジック実装...
    }
}

// Repository
class SysDeployRepository extends _BaseSysRepository
{
    protected string $modelClass = SysDeploy::class;
    protected string $cachePrefix = 'sys:deploy';

    public function selectLatestDownloadable(): ?SysDeploy
    {
        return $this->cacheRemember(
            'latest_downloadable',
            fn() => $this->newQuery()
                ->where('is_active', true)
                ->where('start_at', '<=', ClockUtility::now())
                ->orderBy('deploy_key', 'desc')
                ->first()
        );
    }
}

// Handler (Strategy Pattern)
interface DeliveryHandlerInterface
{
    public function canHandle(DeliveryContent $content): bool;
    public function deliver(int $playerId, DeliveryContent $content): DeliveryResult;
}

class ItemDeliveryHandler implements DeliveryHandlerInterface
{
    public function canHandle(DeliveryContent $content): bool
    {
        return $content->resourceType === 'item';
    }

    public function deliver(int $playerId, DeliveryContent $content): DeliveryResult
    {
        // アイテム配布ロジック
    }
}
```

#### 2. Repository層の型アノテーション（PHPStan Generics）

**すべてのRepository層では、PHPStanテンプレート（ジェネリクス風）アノテーションを使用して型安全性を確保します。**

**目的:**
- PHPStanの静的解析でRepository→Modelの型を正しく推論
- IDEの補完機能を最大限に活用
- バグの早期発見と型安全なコード

**Repositoryの構造と型アノテーションのルール:**

プロジェクトには4系統のRepositoryがあります：

| 系統 | DB接続 | キャッシュ | 用途 |
|-----|--------|---------|-----|
| **Mst** | `mst` | Redis + メモリ | マスターデータ（読み取り専用） |
| **Sys** | `sys` | メモリ + 一部Redis | システムデータ |
| **Trx** | `trx` | メモリ | トランザクションデータ（CRUD） |
| **Log** | `log` | なし | ログデータ（INSERT ONLY） |

**型アノテーションのパターン:**

**1. 基底クラス（`_Base*Repository`）:**

```php
/**
 * @template T of _BaseXxxInterface
 * @implements _BaseXxxRepositoryInterface<T>
 */
abstract class _BaseXxxRepository extends _BaseRepository implements _BaseXxxRepositoryInterface
{
    // ...
}
```

**例: Mst系基底クラス**
```php
/**
 * _BaseMstRepository
 *
 * マスターデータのRepository基底クラス
 * キャッシュ機能を含む読み取り専用操作を提供
 * 
 * @template T of _BaseMstInterface
 * @implements _BaseMstRepositoryInterface<T>
 */
abstract class _BaseMstRepository extends _BaseRepository implements _BaseMstRepositoryInterface
{
    /**
     * IDでマスターレコードを取得
     * 
     * @param int|string $mstRecordId
     * @return T|null
     */
    public function selectById($mstRecordId)
    {
        // ...
    }
}
```

**2. サブクラス（具体的なRepository）:**

```php
/**
 * @extends _BaseXxxRepository<ConcreteModel>
 */
class ConcreteRepository extends _BaseXxxRepository
{
    protected string $modelClass = ConcreteModel::class;
    
    // ...
}
```

**例: Mst系サブクラス**
```php
/**
 * @extends _BaseMstRepository<MstGacha>
 */
class MstGachaRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGacha::class;
    protected string $cachePrefix = 'mst:gacha';
    
    // selectById($id) の戻り値が MstGacha|null と推論される
}
```

**3. Interfaceの型定義（`_Base*RepositoryInterface`）:**

```php
/**
 * @template T of _BaseXxxInterface
 */
interface _BaseXxxRepositoryInterface
{
    /**
     * @param int|string $id
     * @return T|null
     */
    public function selectById($id);
    
    /**
     * @return Collection<int|string, T>
     */
    public function selectAll();
}
```

**4. Modelの型定義（`_Base*Interface`）:**

Modelにも対応するInterfaceを実装します：

```php
/**
 * @property int $id
 * @property string $name
 */
class MstGacha extends _BaseMst implements _BaseMstInterface
{
    // ...
}
```

**全Repository系統の型アノテーションパターン:**

| 系統 | 基底クラス | サブクラス | Interface | Model |
|-----|-----------|----------|-----------|-------|
| **Mst** | `@template T of _BaseMstInterface`<br>`@implements _BaseMstRepositoryInterface<T>` | `@extends _BaseMstRepository<MstXxx>` | `@template T of _BaseMstInterface` | `implements _BaseMstInterface` |
| **Sys** | `@template T of _BaseSysInterface`<br>`@implements _BaseSysRepositoryInterface<T>` | `@extends _BaseSysRepository<SysXxx>` | `@template T of _BaseSysInterface` | `implements _BaseSysInterface` |
| **Trx** | `@template T of _BaseTrxInterface`<br>`@implements _BaseTrxRepositoryInterface<T>` | `@extends _BaseTrxRepository<TrxXxx>` | `@template T of _BaseTrxInterface` | `implements _BaseTrxInterface` |
| **Log** | `@template T of _BaseLogInterface`<br>`@implements _BaseLogRepositoryInterface<T>` | `@extends _BaseLogRepository<LogXxx>` | `@template T of _BaseLogInterface` | `implements _BaseLogInterface` |

**実装例（完全版）:**

```php
// 1. Model Interface
interface _BaseMstInterface {}

// 2. Model
class MstGacha extends _BaseMst implements _BaseMstInterface
{
    protected $connection = 'mst';
    protected $table = 'mst_gacha';
}

// 3. Repository Interface
/**
 * @template T of _BaseMstInterface
 */
interface _BaseMstRepositoryInterface
{
    /**
     * @param int|string $id
     * @return T|null
     */
    public function selectById($id);
}

// 4. 基底Repository
/**
 * @template T of _BaseMstInterface
 * @implements _BaseMstRepositoryInterface<T>
 */
abstract class _BaseMstRepository extends _BaseRepository implements _BaseMstRepositoryInterface
{
    /**
     * @param int|string $mstRecordId
     * @return T|null
     */
    public function selectById($mstRecordId)
    {
        // キャッシュから取得
        return $this->getModel($mstRecordId);
    }
}

// 5. 具体的なRepository
/**
 * @extends _BaseMstRepository<MstGacha>
 */
class MstGachaRepository extends _BaseMstRepository
{
    protected string $modelClass = MstGacha::class;
    protected string $cachePrefix = 'mst:gacha';
}

// 使用例: IDEとPHPStanが正しく型推論
$gachaRepository = app(MstGachaRepository::class);
$gacha = $gachaRepository->selectById('test_gacha'); // MstGacha|null と推論される
if ($gacha !== null) {
    echo $gacha->name; // IDE補完が効く
}
```

**注意事項:**
1. **全50ファイルで統一**: Mst系21, Log系9, Trx系17, Sys系8 + GachaValidationService
2. **@templateタグの位置**: 基底クラスとInterfaceの両方に記載
3. **@extendsタグ**: サブクラスで具体的なModel型を指定
4. **PHPStan Level 8対応**: 型アノテーションがないと静的解析でエラー

#### 3. Delivery経由のコンテンツ配布パターン（重要）

**コンテンツ配布（アイテム、ユニット、装備、ダイヤモンド、通貨等）は、すべてDeliveryシステム経由で実装します。**

**目的:**
- 配布ロジックの一元管理
- コンテンツタイプ別の処理を統一
- ログ記録の自動化
- テスト容易性の向上

**Deliveryシステムの構造:**

```
DeliveryService（統括サービス）
    ↓
DeliveryManager（配送マネージャー、Strategy Pattern実装）
    ↓
各DeliveryHandler（コンテンツタイプ別のハンドラー）
    ├── ItemDeliveryHandler（アイテム配送）
    ├── UnitDeliveryHandler（ユニット配送）
    ├── EquipmentDeliveryHandler（装備配送）
    ├── DiamondDeliveryHandler（ダイヤモンド配送）
    └── WalletDeliveryHandler（通貨配送）
```

**実装パターン:**

**1. DeliveryContent DTO（配布物の定義）:**

```php
readonly class DeliveryContent
{
    public function __construct(
        public string $contentType,  // 'item', 'unit', 'equipment', 'diamond', 'wallet'
        public string $contentId,    // mst_item.id, mst_unit.id 等
        public int $amount,          // 配布数量
    ) {}
    
    // ファクトリメソッド
    public static function item(string $itemId, int $amount): self
    {
        return new self('item', $itemId, $amount);
    }
    
    public static function unit(string $unitId, int $amount): self
    {
        return new self('unit', $unitId, $amount);
    }
    
    public static function equipment(string $equipmentId, int $amount): self
    {
        return new self('equipment', $equipmentId, $amount);
    }
    
    public static function diamond(int $amount, bool $isPaid = false): self
    {
        return new self('diamond', $isPaid ? 'paid' : 'free', $amount);
    }
    
    public static function wallet(string $currencyId, int $amount): self
    {
        return new self('wallet', $currencyId, $amount);
    }
}
```

**2. DeliveryHandler Interface:**

```php
interface DeliveryHandlerInterface
{
    /**
     * このハンドラーが対応できるコンテンツタイプか判定
     */
    public function supports(string $contentType): bool;
    
    /**
     * コンテンツを配送
     */
    public function handle(int $sysPlayerId, DeliveryContent $content): DeliveryResult;
}
```

**3. 具体的なHandler実装例（EquipmentDeliveryHandler）:**

```php
class EquipmentDeliveryHandler implements DeliveryHandlerInterface
{
    public function __construct(
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
        private readonly MstEquipmentRepository $mstEquipmentRepository,
    ) {}

    public function supports(string $contentType): bool
    {
        return $contentType === 'equipment';
    }

    public function handle(int $sysPlayerId, DeliveryContent $content): DeliveryResult
    {
        // 1. マスターデータ検証
        $mstEquipment = $this->mstEquipmentRepository->selectById($content->contentId);
        if ($mstEquipment === null) {
            throw MasterDataException::equipment($content->contentId);
        }

        // 2. 装備作成（複数個の場合はループ）
        $createdIds = [];
        for ($i = 0; $i < $content->amount; $i++) {
            $trxEquipment = $this->trxEquipmentRepository->createEquipment(
                sysPlayerId: $sysPlayerId,
                mstEquipmentId: $content->contentId,
                level: 1,
                exp: 0
            );
            $createdIds[] = $trxEquipment->id;
        }

        // 3. 結果を返す
        return new DeliveryResult(
            contentType: 'equipment',
            contentId: $content->contentId,
            amount: $content->amount,
            details: ['created_ids' => $createdIds]
        );
    }
}
```

**4. DeliveryServiceの使い方:**

```php
// Service層での使用例（GachaPrizeService）
class GachaPrizeService
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {}

    public function grantPrizes(int $sysPlayerId, array $prizes): array
    {
        // 1. 景品をDeliveryContentに変換
        $deliveryContents = [];
        foreach ($prizes as $prize) {
            $deliveryContents[] = match ($prize['content_type']) {
                'item' => DeliveryContent::item($prize['content_id'], $prize['amount']),
                'unit' => DeliveryContent::unit($prize['content_id'], $prize['amount']),
                'equipment' => DeliveryContent::equipment($prize['content_id'], $prize['amount']),
                default => throw BusinessLogicException::unsupportedContentType($prize['content_type']),
            };
        }

        // 2. 一括配送
        $results = $this->deliveryService->delivers($sysPlayerId, $deliveryContents);

        // 3. 結果を返す
        return array_map(fn($result) => [
            'content_type' => $result->contentType,
            'content_id' => $result->contentId,
            'amount' => $result->amount,
        ], $results);
    }
}
```

**5. 新しいコンテンツタイプのHandler追加手順:**

新しいコンテンツタイプ（例: `costume`）を追加する場合：

```php
// 1. Handlerを作成
class CostumeDeliveryHandler implements DeliveryHandlerInterface
{
    public function supports(string $contentType): bool
    {
        return $contentType === 'costume';
    }

    public function handle(int $sysPlayerId, DeliveryContent $content): DeliveryResult
    {
        // 実装...
    }
}

// 2. DeliveryServiceに登録（AppServiceProvider.php）
$this->app->bind(DeliveryManagerInterface::class, function ($app) {
    return new DeliveryManager([
        $app->make(ItemDeliveryHandler::class),
        $app->make(UnitDeliveryHandler::class),
        $app->make(EquipmentDeliveryHandler::class),
        $app->make(DiamondDeliveryHandler::class),
        $app->make(WalletDeliveryHandler::class),
        $app->make(CostumeDeliveryHandler::class),  // ← 追加
    ]);
});

// 3. DeliveryContent にファクトリメソッド追加
public static function costume(string $costumeId, int $amount): self
{
    return new self('costume', $costumeId, $amount);
}
```

**設計の利点:**
- **Open/Closed Principle**: 既存コードを変更せずに新しいコンテンツタイプを追加可能
- **Single Responsibility**: 各Handlerが1つのコンテンツタイプのみを担当
- **Testability**: Handlerを個別にユニットテスト可能
- **Maintainability**: 配布ロジックが一箇所に集約されている

**注意事項:**
1. **直接Repository呼び出しは禁止**: Service層では`TrxItemRepository::addItem()`を直接呼ばず、必ず`DeliveryService::deliver()`経由で実装
2. **サポート対象の確認**: ガチャでは`item`, `unit`, `equipment`のみサポート（`diamond`と`wallet`は非対応）
3. **トランザクション内で使用**: DeliveryServiceはトランザクション内で呼び出すこと（QueryManagerにキューイングされる）

#### 4. テスト用のMstキャッシュクリア機構

**統合テストでマスターデータを動的に作成する場合、Mstリポジトリのキャッシュをクリアする必要があります。**

**問題:**
- `_BaseMstRepository`は初回アクセス時に全データをRedis/メモリにキャッシュする
- テストのsetUp()でマスターデータを作成しても、リポジトリが先にインスタンス化されるとキャッシュが空になる

**解決策:**

**1. _BaseMstRepositoryにキャッシュクリア機構を追加:**

```php
abstract class _BaseMstRepository extends _BaseRepository
{
    /**
     * キャッシュをクリアする（テスト用）
     * Redisキャッシュとメモリキャッシュの両方をクリアする
     */
    public function clearCache(): void
    {
        // メモリキャッシュをクリア
        $this->models = null;

        // Redisキャッシュをクリア
        $modelInstance = new $this->modelClass;
        $tableName = $modelInstance->getTable();
        $cacheKey = "{$this->cachePrefix}:{$tableName}:all";
        
        Cache::store($this->cacheDriver)->forget($cacheKey);
    }

    /**
     * 全てのMstリポジトリのキャッシュをクリアする（テスト用静的メソッド）
     */
    public static function clearAllCaches(): void
    {
        // Redisキャッシュ全体をクリア（mst:*のパターンで削除）
        Cache::store('redis')->flush();
    }
}
```

**2. TestCaseにrefreshMstCache()メソッドを追加:**

```php
abstract class TestCase extends BaseTestCase
{
    /**
     * Mstリポジトリのキャッシュをクリアする
     * テストでマスターデータを作成した後に呼び出すことで、
     * リポジトリが新しいデータを読み込むようにする
     */
    protected function refreshMstCache(): void
    {
        _BaseMstRepository::clearAllCaches();
    }
}
```

**3. 統合テストでの使用例:**

```php
class GachaDrawTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // マスターデータを作成
        $this->createGachaMasterData();
        
        // Mstリポジトリのキャッシュをクリアして、新しく作成したデータを読み込ませる
        $this->refreshMstCache();
    }

    private function createGachaMasterData(): void
    {
        MstGacha::create([...]);
        MstGachaPrize::create([...]);
        // ...
    }

    public function test_gacha_draw_works(): void
    {
        // ガチャ実行テスト
        // Mstリポジトリが正しくマスターデータを読み込む
    }
}
```

**設計の利点:**
- **テストの独立性**: 各テストで異なるマスターデータを使用可能
- **シンプルなAPI**: `refreshMstCache()`を呼ぶだけでOK
- **本番環境への影響なし**: テスト専用メソッドとして明示

**注意事項:**
1. **本番環境では使用しない**: `clearCache()`と`clearAllCaches()`はテスト専用
2. **Database Seederとの併用**: 大量のマスターデータはSeederで事前投入し、テスト固有データのみ動的作成を推奨
3. **パフォーマンス**: Redisキャッシュのflush()は全キーを削除するため、テスト間で共有データがある場合は個別の`clearCache()`を使用

### Infrastructure Layer（インフラストラクチャ層）

**責務:**
- データベースアクセス
- 外部システムとの連携
- 技術的な実装詳細

**コンポーネント:**
- **Model**: Eloquentモデル（データアクセス）

**実装例:**

```php
class SysDeploy extends Model
{
    protected $connection = 'sys';
    protected $table = 'sys_deploy';

    protected $casts = [
        'is_active' => 'boolean',
        'start_at' => 'immutable_datetime',
        'end_at' => 'immutable_datetime',
    ];

    // リレーション定義
    public function deployMaster(): BelongsTo
    {
        return $this->belongsTo(SysDeployMaster::class, 'sys_deploy_master_id');
    }

    // クエリスコープ
    public function scopeDownloadable(Builder $query): void
    {
        $query->where('is_active', true)
              ->where('start_at', '<=', ClockUtility::now());
    }
}
```

---

## データフロー

### リクエストからレスポンスまでの流れ

```
1. HTTPリクエスト
   ↓
2. Controller（Presentation Layer）
   ├── Request（バリデーション）
   └── UseCase呼び出し
   ↓
3. UseCase（Application Layer）
   ├── トランザクション開始
   ├── Service呼び出し（複数可）
   └── トランザクション終了
   ↓
4. Service（Domain Layer）
   ├── ビジネスロジック実行
   ├── Repository/Model経由でデータアクセス
   └── DTOを返す
   ↓
5. Controller（Presentation Layer）
   ├── Response生成
   └── toJsonResponse()
   ↓
6. HTTPレスポンス
```

### 具体例: バージョンチェックAPI

```
POST /auth/version

1. AuthController::version()
   ├── VersionCheckRequest（バリデーション）
   ├── $deployVersion = $request->getDeployVersion()
   └── VersionCheckUseCase::handle($deployVersion)
   
2. VersionCheckUseCase::handle(?int $deployVersion)
   └── VersionCheckService::checkVersion($deployVersion)
   
3. VersionCheckService::checkVersion(?int $deployVersion)
   ├── SysDeploy::getLatestDownloadable()（Model）
   ├── ビジネスロジック実行
   └── VersionCheckResponse生成（DTO）
   
4. AuthController::version()
   └── VersionCheckResponse::toJsonResponse()

→ JSONレスポンス
```

---

## 設計パターン

### 1. Repository Pattern（リポジトリパターン）

**目的**: データアクセスの抽象化とキャッシュ管理

```php
// Repository（Domain Layer）
class SysPlayerRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayer::class;
    protected string $cachePrefix = 'sys:player';

    public function selectByMyId(string $myId): ?SysPlayer
    {
        return $this->cacheRemember(
            "my_id:{$myId}",
            fn() => $this->newQuery()->where('my_id', $myId)->first()
        );
    }
}
```

### 2. Strategy Pattern（戦略パターン）

**目的**: 配布処理などの種類別ロジックの切り替え

```php
// Handler Interface（Domain Layer）
interface DeliveryHandlerInterface
{
    public function canHandle(DeliveryContent $content): bool;
    public function deliver(int $playerId, DeliveryContent $content): DeliveryResult;
}

// Concrete Handler
class ItemDeliveryHandler implements DeliveryHandlerInterface
{
    public function canHandle(DeliveryContent $content): bool
    {
        return $content->resourceType === 'item';
    }

    public function deliver(int $playerId, DeliveryContent $content): DeliveryResult
    {
        // アイテム配布ロジック
    }
}

// Service（Domain Layer）
class DeliveryService
{
    public function __construct(
        private readonly array $handlers  // DI Container で注入
    ) {}

    public function deliver(int $playerId, DeliveryContent $content): DeliveryResult
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($content)) {
                return $handler->deliver($playerId, $content);
            }
        }
        
        throw new UnsupportedResourceTypeException($content->resourceType);
    }
}
```

---

## まとめ

### 重要な原則

1. **単一責任の原則**: 各クラスは1つの責務のみを持つ
2. **依存性逆転の原則**: 上位層は下位層に依存するが、下位層は上位層に依存しない
3. **開放閉鎖の原則**: 拡張に対して開いており、修正に対して閉じている（Strategy Pattern）
4. **インターフェース分離の原則**: クライアントは使用しないメソッドに依存しない

### レイヤー別のチェックリスト

**Controller（Presentation Layer）:**
- [ ] 10行以内に収まっているか
- [ ] ビジネスロジックを含んでいないか
- [ ] UseCaseに処理を委譲しているか

**UseCase（Application Layer）:**
- [ ] 1つのユースケースを表現しているか
- [ ] トランザクション管理を適切に行っているか
- [ ] Serviceを組み合わせているか

**Service（Domain Layer）:**
- [ ] ビジネスロジックを実装しているか
- [ ] HTTPリクエスト/レスポンスに依存していないか
- [ ] 再利用可能な設計になっているか

**Model（Infrastructure Layer）:**
- [ ] データアクセスのみを担当しているか
- [ ] ビジネスロジックを含んでいないか
- [ ] リレーションとスコープを適切に定義しているか

---

## コード品質の保証

### 静的解析とアーキテクチャテスト

このプロジェクトでは、コード品質とアーキテクチャの整合性を保つために、以下のツールを使用しています：

#### PHPStan（静的解析）

PHPStanは、コードを実行せずに型エラーやバグを検出するツールです。

**実行方法:**
```bash
docker exec api-php composer phpstan
```

**カスタムルール:**
- **Service層でのEloquent直接操作禁止**: Service層で`save()`, `update()`, `delete()`を直接呼び出すことを禁止

#### アーキテクチャテスト

PHPUnitベースのテストで、コードベースの構造的な制約を検証します。

**実行方法:**
```bash
docker exec api-php composer test:arch
```

**検証項目:**
- Service層でのEloquent直接操作禁止
- 命名規約（Service/UseCase/Repository）
- レイヤー間の依存関係

**詳細はこちら:** [静的解析とアーキテクチャテスト](../api/docs/static-analysis.md)

---

## 論理削除システム

このプロジェクトでは、trxテーブルに対して**論理削除→物理削除**の仕組みを実装しています。

### 設計思想

**なぜ論理削除を使用するのか:**
- トランザクション整合性の確保（外部キー参照の問題を回避）
- データの即時削除を避け、後で一括削除することでパフォーマンスを向上
- ロールバックや復元の可能性を残す

**物理削除のタイミング:**
- サインイン時のみ実行（`sign_in` API）
- プレイヤーごとに、そのプレイヤーの`is_delete=true`レコードをすべて削除

### データベース構造

すべてのtrxテーブルには`is_delete`カラムが存在します：

```sql
ALTER TABLE trx_* ADD COLUMN is_delete TINYINT(1) NOT NULL DEFAULT 0 COMMENT '論理削除フラグ';
```

**対象テーブル（全12テーブル）:**
- `trx_player`
- `trx_player_sns`
- `trx_unit`
- `trx_item`
- `trx_equipment`
- `trx_stamina`
- `trx_in_app_purchase`
- `trx_in_app_purchase_effect`
- `trx_diamond`
- `trx_diamond_balance`
- `trx_wallet`
- `trx_wallet_balance`

### 実装の仕組み

#### 1. Repository層の拡張

**_BaseRepository:**

```php
abstract class _BaseRepository
{
    // 削除キュー（物理削除対象のモデル）
    protected array $deleteQueue = [];
    
    /**
     * 削除キューに登録されたモデルを取得
     */
    public function getQueuedDeleteModels(): array
    {
        return $this->deleteQueue;
    }
}
```

**_BaseTrxRepository:**

```php
abstract class _BaseTrxRepository extends _BaseRepository
{
    /**
     * モデルを削除キューに追加（単一）
     */
    public function deleteModel($model): void
    {
        $this->deleteQueue[] = $model;
        
        // QueryManagerに自身を登録
        if (!$this->registeredToManager) {
            $queryManager = app()->make(\App\Repositories\TrxQueryManager::class);
            $queryManager->registerRepository($this);
            $this->registeredToManager = true;
        }
    }
    
    /**
     * モデルを削除キューに追加（複数）
     */
    public function deleteModels(Collection $models): void
    {
        foreach ($models as $model) {
            $this->deleteQueue[] = $model;
        }
        
        // QueryManagerに自身を登録
        if (!$this->registeredToManager) {
            $queryManager = app()->make(\App\Repositories\TrxQueryManager::class);
            $queryManager->registerRepository($this);
            $this->registeredToManager = true;
        }
    }
    
    /**
     * is_delete=true のレコードを削除キューに追加
     */
    public function deleteMarkedRecords(int $sysPlayerId): void
    {
        $selectKey = $this->getSelectKey();
        $models = $this->newQuery()
            ->where($selectKey, $sysPlayerId)
            ->where('is_delete', true)
            ->get();
        
        if ($models->isNotEmpty()) {
            $this->deleteModels($models);
        }
    }
}
```

#### 2. TrxQueryManagerの拡張

DELETE処理をINSERT/UPDATEと同様にキューイングして一括実行します：

```php
namespace App\Repositories;

class QueryManager
{
    public function execAllQuery(): void
    {
        foreach ($this->repositories as $repository) {
            $connection = $repository->getConnection();
            
            // 1. INSERT実行
            $insertModels = $repository->getQueuedInsertModels();
            if (!empty($insertModels)) {
                // Sysの場合、3つのテーブルのみ個別INSERT、その他はバッチINSERT
                // Trx/Logの場合、全てバッチINSERT
            }
            
            // 2. UPDATE実行
            $updateModels = $repository->getQueuedUpdateModels();
            foreach ($updateModels as $model) {
                // UPDATE...
            }
            
            // 3. DELETE実行
            $deleteModels = $repository->getQueuedDeleteModels();
            foreach ($deleteModels as $model) {
                $model->delete();  // 物理削除
            }
        }
    }
}
```

#### 3. PlayerCleanupService

プレイヤーの全テーブルから`is_delete=true`レコードを削除キューに追加するサービス：

```php
class PlayerCleanupService
{
    public function __construct(
        private readonly TrxPlayerRepository $trxPlayerRepository,
        private readonly TrxPlayerSnsRepository $trxPlayerSnsRepository,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly TrxItemRepository $trxItemRepository,
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
        private readonly TrxStaminaRepository $trxStaminaRepository,
        private readonly TrxInAppPurchaseEffectRepository $trxInAppPurchaseEffectRepository,
        private readonly TrxInAppPurchaseRepository $trxInAppPurchaseRepository,
        private readonly TrxDiamondRepository $trxDiamondRepository,
        private readonly TrxDiamondBalanceRepository $trxDiamondBalanceRepository,
        private readonly TrxWalletRepository $trxWalletRepository,
        private readonly TrxWalletBalanceRepository $trxWalletBalanceRepository,
    ) {}
    
    /**
     * プレイヤーの全削除マークレコードを削除キューに追加
     */
    public function cleanup(int $sysPlayerId): void
    {
        $this->trxPlayerRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxPlayerSnsRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxUnitRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxItemRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxEquipmentRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxStaminaRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxInAppPurchaseEffectRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxInAppPurchaseRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxDiamondRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxDiamondBalanceRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxWalletRepository->deleteMarkedRecords($sysPlayerId);
        $this->trxWalletBalanceRepository->deleteMarkedRecords($sysPlayerId);
    }
}
```

#### 4. UseCaseTraitの拡張

トランザクション開始**前**にクリーンアップ処理を実行します：

```php
trait UseCaseTrait
{
    /**
     * トランザクション内でコールバックを実行
     * 
     * @param callable $callback ビジネスロジック（SELECT + キューイング）
     * @param int|null $sysPlayerId プレイヤーID（指定時はクリーンアップ実行）
     * @return mixed コールバックの戻り値
     */
    protected function executeWithTransaction(callable $callback, ?int $sysPlayerId = null)
    {
        // 1. コールバック実行（SELECT + キューイング）
        $result = $callback();
        
        // 2. クリーンアップ処理（$sysPlayerIdが指定されている場合のみ）
        if ($sysPlayerId !== null) {
            $cleanupService = app(PlayerCleanupService::class);
            $cleanupService->cleanup($sysPlayerId);
        }
        
        // 3. トランザクション開始
        DB::connection('trx')->beginTransaction();
        DB::connection('log')->beginTransaction();
        
        try {
            // 4. キューイングされたクエリを一括実行（INSERT → UPDATE → DELETE）
            app(\App\Repositories\QueryManager::class)->execPurchaseQuery();  // 課金ログ
            app(\App\Repositories\QueryManager::class)->execAllQuery();       // Sys/Trx/Log
            
            // 5. コミット
            DB::connection('sys')->commit();
            DB::connection('trx')->commit();
            DB::connection('log')->commit();
            
            return $result;
        } catch (\Exception $e) {
            // 6. ロールバック
            DB::connection('sys')->rollBack();
            DB::connection('trx')->rollBack();
            DB::connection('log')->rollBack();
            throw $e;
        }
    }
}
```

### 使用方法

#### 通常のAPI処理（論理削除のみ）

削除したいレコードに`is_delete=true`を設定します：

```php
class SomeService
{
    public function deleteUnit(int $trxUnitId): void
    {
        $unit = $this->trxUnitRepository->findById($trxUnitId);
        $unit->is_delete = true;
        $this->trxUnitRepository->setModel($unit);  // キューイング（物理削除はしない）
    }
}
```

#### Sign-in時（物理削除の実行）

`executeWithTransaction()`の第2引数に`$sysPlayerId`を渡します：

```php
class SignInUseCase
{
    use UseCaseTrait;
    
    public function handle(string $refreshToken, string $idToken, ?string $appleUserId): SignInResponse
    {
        // バリデーション...
        
        return $this->executeWithTransaction(
            callback: function () use ($tokenModel) {
                // ビジネスロジック...
                return new SignInResponse(...);
            },
            sysPlayerId: $tokenModel->sys_player_id  // ← クリーンアップ実行
        );
    }
}
```

### 実行フロー

```
1. executeWithTransaction() 呼び出し
   ↓
2. コールバック実行（SELECTとキューイング）
   ↓
3. PlayerCleanupService::cleanup($sysPlayerId)
   ├── 全trxテーブルからis_delete=trueレコードを検索
   └── deleteQueue に追加
   ↓
4. トランザクション開始
   ↓
5. TrxQueryManager::execAllQuery()
   ├── INSERT実行
   ├── UPDATE実行
   └── DELETE実行（is_delete=trueレコードを物理削除）
   ↓
6. トランザクションコミット
```

### 自動削除の例：パス効果の期限切れ

有効期限切れのパス効果は、自動的に`is_delete=true`になります：

```php
class TrxInAppPurchaseEffectRepository extends _BaseTrxRepository
{
    /**
     * 有効な効果を取得（期限切れは自動的にis_delete=trueに設定）
     */
    public function getActiveEffects(int $sysPlayerId): Collection
    {
        $now = ClockUtility::now();
        
        // 1. 全効果を取得
        $allEffects = $this->selectAll($sysPlayerId);
        
        // 2. 有効/無効を判定
        $activeEffects = collect();
        foreach ($allEffects as $effect) {
            if ($effect->expire_at !== null && $effect->expire_at < $now) {
                // 期限切れ → 論理削除フラグを立てる
                $effect->is_delete = true;
                $this->setModel($effect);
            } else {
                // 有効
                $activeEffects->push($effect);
            }
        }
        
        return $activeEffects;
    }
}
```

次回のsign_in時に、これらの期限切れ効果が物理削除されます。

### 設計の利点

1. **パフォーマンス向上**: 削除を即時実行せず、sign_in時に一括実行
2. **トランザクション整合性**: 外部キー制約の問題を回避
3. **復元可能性**: 論理削除されたデータは物理削除前なら復元可能
4. **デバッグ容易性**: 削除予定のデータを確認できる
5. **ログ記録**: 削除前にログを記録できる

### 注意事項

1. **is_deleteカラムの追加**: すべてのtrxテーブルに`is_delete`カラムが必要
2. **$fillableへの追加**: すべてのtrxモデルの`$fillable`に`is_delete`を追加
3. **sign_in以外では物理削除しない**: 通常のAPIでは`is_delete=true`の設定のみ
4. **WHERE句の考慮**: クエリ時に`is_delete=false`の条件を追加する場合もある

---

## ApiSession（プレイヤーID管理）

このプロジェクトでは、**ApiSession**を使用してAPIリクエストのコンテキスト情報（プレイヤーID等）を管理します。

### 設計思想

1. **唯一のプレイヤーID管理方法**: Repository内で`$sysPlayerId`フィールドを持たず、ApiSessionから動的に取得
2. **Middleware統合**: 認証時に自動的にApiSessionにプレイヤーIDを設定
3. **パフォーマンス最適化**: Repository内でキャッシュ機構を実装し、2回目以降はキャッシュを使用
4. **クリーンなコード**: Repository/Service/UseCaseのメソッドシグネチャが簡潔になる

### 実装パターン

```php
// Middleware（認証時）
class VerifyAccessToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->verifyAccessToken($accessToken);
        
        // ApiSessionにプレイヤーIDを設定
        ApiSession::setSysPlayerId($token->sys_player_id);
        
        return $next($request);
    }
}

// Repository（ApiSessionから自動取得）
abstract class _BaseTrxRepository extends _BaseRepository
{
    protected ?int $cachedSysPlayerId = null;

    protected function getSysPlayerId(): int
    {
        // 初回のみApiSessionから取得、以降はキャッシュ
        if ($this->cachedSysPlayerId === null) {
            $this->cachedSysPlayerId = ApiSession::getSysPlayerId();
        }

        return $this->cachedSysPlayerId;
    }
}

// Service（プレイヤーIDを意識する必要がない）
class UnitLevelService
{
    public function addExp(int $trxUnitId, int $exp): array
    {
        // Repositoryが内部でApiSessionを使用
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        
        // ...
    }
}
```

**詳細は [api/api-session.md](./api/api-session.md) を参照してください。**

---

## 関連ドキュメント

- [コーディング規約](./coding-standards.md) - 各層の実装ルール
- [命名規約](./naming-conventions.md) - クラスとディレクトリの命名規則
- [データベース設計](./database.md) - データベース層の設計
- [API設計](./api.md) - APIエンドポイントの設計
- [ApiSession実装ルール](./api/api-session.md) - プレイヤーID管理の詳細
- [静的解析とアーキテクチャテスト](../api/docs/static-analysis.md) - コード品質の保証
