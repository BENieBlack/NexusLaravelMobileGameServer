# 命名規約 / Naming Conventions

このドキュメントでは、プロジェクト全体で使用する命名規約を定義します。

## 目次

- [基本原則](#基本原則)
- [ケーススタイルの統一ルール](#ケーススタイルの統一ルール)
- [ディレクトリ命名](#ディレクトリ命名)
- [クラス命名ルール](#クラス命名ルール)
- [DTOクラスの命名](#dtoクラスの命名)
- [Responseクラスの命名](#responseクラスの命名)
- [適用例](#適用例)
- [既存コードのリファクタリング](#既存コードのリファクタリング)

---

## 基本原則

**一貫性のある命名でコードの可読性を向上**

このプロジェクトでは、クラスとディレクトリの命名に一貫性のあるルールを適用します。

### 重要な原則

1. **ディレクトリ名でクラスの種類を明示**: クラス名にサフィックスを付けることで冗長性を避ける
2. **Laravelの慣習に従う**: 複数形ディレクトリ（Controllers, Services等）
3. **シンプルで読みやすい命名**: 不要なサフィックスは避ける
4. **データソースの明示**: ID変数は必ずデータベースプレフィックスを含める（`$mstItemId`, `$trxUnitId`）

---

## ケーススタイルの統一ルール

**重要: テーブル名・カラム名・配列キーは全てsnake_case、変数名・関数名はcamelCaseを使用**

プロジェクト全体で、一貫性のあるケーススタイルを使用します。

### 基本ルール

| 対象 | ケーススタイル | 例 |
|-----|-------------|-----|
| **テーブル名** | `snake_case` | `sys_player`, `trx_equipment`, `mst_item` |
| **カラム名** | `snake_case` | `sys_player_id`, `mst_unit_id`, `created_at` |
| **テーブルIDカラム** | `{テーブル名}_id` | `sys_friend_apply_id` (sys_friend_applyテーブルのid) |
| **配列キー（全て）** | `snake_case` | `'sys_player'`, `'is_leveled_up'`, `'before_level'` |
| **変数名** | `camelCase` | `$sysPlayer`, `$trxEquipment`, `$isLeveledUp` |
| **関数名** | `camelCase` | `createPlayer()`, `findByDeviceId()`, `addExp()` |
| **クラス名** | `PascalCase` | `SysPlayer`, `PlayerService`, `SignUpUseCase` |

### ✅ Good: 正しいケーススタイルの使用

```php
// データベース定義（snake_case）
CREATE TABLE sys_player (
    id INT UNSIGNED PRIMARY KEY,
    my_id VARCHAR(8) UNIQUE NOT NULL,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE TABLE trx_equipment (
    id BIGINT UNSIGNED PRIMARY KEY,
    sys_player_id INT UNSIGNED NOT NULL,
    mst_equipment_id VARCHAR(64) NOT NULL,
    current_level INT NOT NULL,
    current_exp INT NOT NULL
);
```

```php
// PHP変数・関数（camelCase）、配列キー（snake_case）
class PlayerService
{
    public function createPlayer(string $deviceId, ?array $deviceInfo = null): array
    {
        $sysPlayer = SysPlayer::create([
            'uuid' => $uuid,           // ← カラム名はsnake_case
            'my_id' => $myId,          // ← カラム名はsnake_case
        ]);

        $sysPlayerDevice = SysPlayerDevice::create([
            'sys_player_id' => $sysPlayer->id,  // ← カラム名はsnake_case
            'device_info' => $deviceInfo,       // ← カラム名はsnake_case
            'last_login_at' => now(),           // ← カラム名はsnake_case
        ]);

        return [
            'sys_player' => $sysPlayer,              // ← 配列キーはsnake_case
            'sys_player_device' => $sysPlayerDevice,  // ← 配列キーはsnake_case
        ];
    }
}
```

```php
// Response（配列キーはsnake_case）
class LevelUpResponse extends _BaseResponse
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,  // ← 変数名はcamelCase
        public readonly TrxItem $trxItem,            // ← 変数名はcamelCase
        public readonly bool $isLeveledUp,           // ← 変数名はcamelCase
        public readonly int $beforeLevel,            // ← 変数名はcamelCase
        public readonly int $afterLevel,             // ← 変数名はcamelCase
    ) {}

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'trx_equipment' => $this->trxEquipment->toResponseArray(),  // ← キーはsnake_case
            'trx_item' => $this->trxItem->toResponseArray(),            // ← キーはsnake_case
            'is_leveled_up' => $this->isLeveledUp,                     // ← キーはsnake_case
            'before_level' => $this->beforeLevel,                      // ← キーはsnake_case
            'after_level' => $this->afterLevel,                        // ← キーはsnake_case
        ]);
    }
}
```

### ❌ Bad: 混在したケーススタイル

```php
// ❌ Bad: 配列キーにcamelCaseを使用
return [
    'sysPlayer' => $sysPlayer,              // ❌ 配列キーはsnake_caseにすべき
    'sysPlayerDevice' => $sysPlayerDevice,  // ❌
];

// ❌ Bad: 変数名にsnake_caseを使用
$sys_player = SysPlayer::create([...]);  // ❌ $sysPlayer とすべき
$trx_equipment = TrxEquipment::find($id);  // ❌ $trxEquipment とすべき

// ❌ Bad: 関数名にsnake_caseを使用
public function create_player(string $device_id): array  // ❌ createPlayer とすべき
{
    // ...
}

// ❌ Bad: ResponseのJSONキーにcamelCaseを使用
return response()->json([
    'isLeveledUp' => true,    // ❌ is_leveled_up とすべき
    'beforeLevel' => 5,       // ❌ before_level とすべき
    'afterLevel' => 6,        // ❌ after_level とすべき
]);
```

### Eloquentモデルの`toResponseArray()`での変換

EloquentモデルをJSONレスポンスに変換する際、**カラム名はsnake_caseのまま**出力します。

```php
// _BaseModel.php
abstract class _BaseModel extends Model
{
    public function toResponseArray(): array
    {
        $array = $this->toArray();
        
        // sys_player_id は除外（内部IDのため）
        unset($array['sys_player_id']);
        
        return $array;  // ← カラム名はsnake_caseのまま
    }
}
```

```php
// APIレスポンス例（全てsnake_case）
{
    "trx_equipment": {
        "id": 123,
        "mst_equipment_id": "equipment_sword_001",
        "current_level": 10,
        "current_exp": 500
    },
    "is_leveled_up": true,
    "before_level": 9,
    "after_level": 10
}
```

### 設計の利点

1. **シンプルで一貫性がある**
   - 全ての配列キーが`snake_case`で統一
   - カラム名とその他のキーを区別する必要がない
   - ルールが明確で迷わない

2. **データベース構造との整合性**
   - JSONレスポンスがデータベースのテーブル構造を反映
   - カラム名をそのまま使用できる
   - フロントエンドでもデータ構造が理解しやすい

3. **変換処理が不要**
   - `snake_case` → `camelCase` の変換が不要
   - パフォーマンスの向上
   - バグの混入リスクが減る

4. **PHP標準との調和**
   - PHPのコード内部は`camelCase`（変数・関数）
   - データ構造（配列キー）は`snake_case`
   - コードとデータの境界が明確

---

## 変数・パラメータの命名規則

### Eloquentモデルインスタンスの命名規則

**重要: Eloquentモデルのインスタンス変数名は、テーブル名のプレフィックス付きで命名する**

Responseクラスやその他のクラスでEloquentモデルのインスタンスを保持する場合、変数名は必ずテーブル名のプレフィックス（`sys_`, `mst_`, `trx_`等）を含めて命名します。

#### ルール

```php
// ✅ Good: テーブル名のプレフィックスを含める
public readonly TrxEquipment $trxEquipment;
public readonly MstUnit $mstUnit;
public readonly SysPlayer $sysPlayer;
public readonly TrxItem $trxItem;

// ❌ Bad: プレフィックスなし（曖昧）
public readonly TrxEquipment $equipment;  // ❌ テーブル名が不明確
public readonly MstUnit $unit;            // ❌ マスターデータか不明確
public readonly SysPlayer $player;        // ❌ どのテーブルか不明確
```

#### Responseクラスでの使用例

```php
// ✅ Good: 明確なテーブル名プレフィックス
namespace App\Http\Responses\Equipment;

use App\Models\Trx\TrxEquipment;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class LevelUpResponse implements Responsable
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,  // ✅ trx_equipment テーブル
    ) {
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'equipment' => [
                'id' => $this->trxEquipment->id,
                'sys_player_id' => $this->trxEquipment->sys_player_id,
                'mst_equipment_id' => $this->trxEquipment->mst_equipment_id,
                // ...
            ],
        ]);
    }
}
```

```php
// ❌ Bad: プレフィックスなし（どのテーブルか不明確）
class LevelUpResponse implements Responsable
{
    public function __construct(
        public readonly TrxEquipment $equipment,  // ❌ テーブル名が不明確
    ) {
    }
}
```

#### UseCaseでの使用例

```php
// ✅ Good: テーブル名プレフィックス付き
class LevelUpUseCase
{
    public function handle(int $sysPlayerId, int $trxEquipmentId, string $mstItemId, int $useCount): LevelUpResponse
    {
        // ...
        $trxEquipment = $this->trxEquipmentRepository->findById($trxEquipmentId);  // ✅
        $mstItem = $this->mstItemRepository->selectById($mstItemId);              // ✅
        
        return new LevelUpResponse(
            trxEquipment: $trxEquipment,  // ✅ 名前付き引数もプレフィックス付き
        );
    }
}
```

```php
// ❌ Bad: プレフィックスなし
class LevelUpUseCase
{
    public function handle(int $sysPlayerId, int $trxEquipmentId, string $mstItemId, int $useCount): LevelUpResponse
    {
        // ...
        $equipment = $this->trxEquipmentRepository->findById($trxEquipmentId);  // ❌
        $item = $this->mstItemRepository->selectById($mstItemId);              // ❌
        
        return new LevelUpResponse(
            equipment: $equipment,  // ❌
        );
    }
}
```

#### 設計の利点

1. **データソースが一目瞭然**
   - 変数名を見るだけでどのテーブルのデータか分かる
   - `$trxEquipment` → `trx_equipment` テーブル
   - `$mstUnit` → `mst_unit` テーブル

2. **コードレビューの効率化**
   - レビュー時に誤ったテーブルのデータを使っていないか即座に判断可能
   - バグを早期に発見できる

3. **IDEの補完が効率的**
   - `$trx` と入力すると、トランザクションテーブル関連の変数のみが候補に表示される
   - 変数の検索・リファクタリングが容易

4. **保守性の向上**
   - 複数のモデルを扱うコードでも、どのテーブルのデータか明確
   - 新規メンバーがコードを理解しやすい

#### Serviceクラスでのモデルインスタンス命名

**重要: Serviceクラスのメソッドパラメータでも、Eloquentモデルインスタンスは必ずテーブル名プレフィックス付きで命名する**

```php
// ✅ Good: テーブル名プレフィックス付き
namespace App\Domain\InAppPurchase\Services;

class DiamondService
{
    public function purchaseDiamond(
        int $sysPlayerId,
        MstInAppPurchase $mstInAppPurchase,  // ✅ mst_in_app_purchase テーブル
        string $platform,
        string $billingPlatform,
        float $unitPrice
    ): array {
        // $mstInAppPurchase->paid_diamond_amount でアクセス
        $diamond->paid_amount += $mstInAppPurchase->paid_diamond_amount;
        // ...
    }
}
```

```php
// ❌ Bad: プレフィックスなし（曖昧）
class DiamondService
{
    public function purchaseDiamond(
        int $sysPlayerId,
        MstInAppPurchase $product,  // ❌ どのテーブルか不明確
        string $platform,
        string $billingPlatform,
        float $unitPrice
    ): array {
        // ...
    }
}
```

**適用例: InAppPurchase機能**

```php
// DiamondService.php - ダイヤモンド購入サービス
public function purchaseDiamond(
    int $sysPlayerId,
    MstInAppPurchase $mstInAppPurchase,  // ✅ 明確
    string $platform,
    string $billingPlatform,
    float $unitPrice
): array { /* ... */ }

// PackService.php - パック購入サービス
public function purchasePack(
    int $sysPlayerId,
    MstInAppPurchase $mstInAppPurchase,  // ✅ 明確
    string $platform,
    string $billingPlatform
): array { /* ... */ }

// PassService.php - パス購入サービス
public function applyPassEffects(
    int $sysPlayerId,
    MstInAppPurchase $mstInAppPurchase  // ✅ 明確
): void { /* ... */ }

// ValidationService.php - 購入制限チェックサービス
public function validatePurchaseLimit(
    MstInAppPurchase $mstInAppPurchase,  // ✅ 明確
    ?TrxInAppPurchase $purchaseHistory,
    string $billingPlatform
): void { /* ... */ }
```

**理由:**
- Serviceクラスは複数のモデルを扱うことが多い
- `$product` のような汎用的な名前では、どのテーブルのデータか判別できない
- `$mstInAppPurchase` とすることで、マスターデータであることが一目瞭然
- コードレビュー時に誤った使い方を即座に発見できる

---

## EloquentモデルとDTOのプロパティアクセスパターン

### 基本方針

**DTOとEloquentモデルで異なるアクセスパターンを採用**

| 種類 | アクセスパターン | 理由 |
|------|----------------|------|
| **DTO** | `public readonly` プロパティ | 不変性を保証、シンプルで安全 |
| **Mstモデル** | Laravelの標準パターン（`$fillable` + `$casts`） | 読み取り専用マスターデータ |
| **Trx/Sys/Logモデル** | Laravelの標準パターン（`$fillable` + `$casts`） | Eloquentのマジックメソッドで保護 |

### DTOのプロパティアクセス

**ルール: DTOは `public readonly` プロパティを使用**

PHP 8.1以降の`readonly`キーワードは、プロパティの不変性を言語レベルで保証します。

```php
// ✅ Good: readonly プロパティ（推奨）
namespace App\Domain\Auth\DTOs;

class DtoToken
{
    public function __construct(
        public readonly string $accessToken,   // ✅ コンストラクタで初期化後は変更不可
        public readonly string $refreshToken,  // ✅ 不変性が保証される
        public readonly int $expiresIn,        // ✅ 型安全
    ) {
    }

    // getter/setterは不要（readonlyで十分）
}
```

```php
// ❌ Bad: private + getter/setter（冗長）
class DtoToken
{
    private string $accessToken;
    private string $refreshToken;
    private int $expiresIn;

    public function __construct(string $accessToken, string $refreshToken, int $expiresIn)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresIn = $expiresIn;
    }

    public function getAccessToken(): string { return $this->accessToken; }
    public function getRefreshToken(): string { return $this->refreshToken; }
    public function getExpiresIn(): int { return $this->expiresIn; }
    // ❌ 不要なボイラープレートコード
}
```

**readonly の利点:**
1. **言語レベルの不変性保証**: 初期化後の変更を防ぐ
2. **シンプル**: getter/setterの記述が不要
3. **型安全**: 型ヒントが必須
4. **パフォーマンス**: オーバーヘッドなし

### Eloquent Modelのプロパティアクセス

**ルール: Laravelの標準パターン（`$fillable` + `$casts` + Eloquentマジックメソッド）を使用**

Laravelでは、プロパティに直接アクセスしても、内部で`getAttribute()`/`setAttribute()`が呼ばれるため安全です。

```php
// ✅ Good: Laravel標準パターン
namespace App\Models\Trx;

class TrxDiamond extends _BaseTrx
{
    protected $table = 'trx_diamond';

    // ✅ マスアサインメント保護
    protected $fillable = [
        'sys_player_id',
        'platform',
        'paid_amount',
        'free_amount',
    ];

    // ✅ 型変換の定義
    protected $casts = [
        'sys_player_id' => 'integer',
        'paid_amount' => 'integer',
        'free_amount' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
```

**使用例:**

```php
// ✅ Good: Eloquentのマジックメソッドで安全にアクセス
$diamond = TrxDiamond::find($id);

// プロパティアクセス（内部でgetAttribute()が呼ばれる）
$paidAmount = $diamond->paid_amount;  // ✅ 型変換も自動

// プロパティ設定（内部でsetAttribute()が呼ばれる）
$diamond->paid_amount += 100;  // ✅ fillableで許可されたプロパティのみ設定可能

// 保存
$diamond->save();  // ✅ バリデーションとトランザクション処理
```

```php
// ❌ Bad: 明示的なgetter/setter（Laravelでは一般的ではない）
class TrxDiamond extends _BaseTrx
{
    private int $paidAmount;  // ❌ Eloquentのマジックメソッドが使えない

    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(int $value): void
    {
        $this->paidAmount = $value;
    }
    // ❌ Laravelのエコシステムと相性が悪い
}
```

**Laravel標準パターンの利点:**

1. **Eloquentとの統合**: マジックメソッド、リレーション、スコープがシームレスに動作
2. **マスアサインメント保護**: `$fillable`で許可されたプロパティのみ設定可能
3. **自動型変換**: `$casts`で定義した型に自動変換
4. **コミュニティ標準**: Laravel開発者にとって自然で読みやすい
5. **保守性**: 冗長なgetter/setterコードが不要

### 特別な処理が必要な場合のアクセサ/ミューテータ

複雑なロジックが必要な場合は、Laravelのアクセサ/ミューテータを使用：

```php
// ✅ Good: 特別な処理が必要な場合
namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Casts\Attribute;

class TrxDiamond extends _BaseTrx
{
    // ...

    /**
     * 合計ダイヤモンド数を取得（paid + free）
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->paid_amount + $this->free_amount,
        );
    }

    /**
     * 有償ダイヤモンド残高のバリデーション付き設定
     */
    protected function paidAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: function ($value) {
                if ($value < 0) {
                    throw new \InvalidArgumentException('Paid amount cannot be negative');
                }
                return $value;
            },
        );
    }
}

// 使用例
$diamond->total_amount;  // ✅ 自動計算される（getter）
$diamond->paid_amount = 100;  // ✅ バリデーションが実行される（setter）
```

### まとめ

| 対象 | パターン | 理由 |
|------|---------|------|
| **DTO** | `public readonly` | 不変性保証、シンプル、型安全 |
| **Eloquent Model** | `$fillable` + `$casts` + マジックメソッド | Laravel標準、エコシステムとの統合 |
| **特別な処理が必要** | アクセサ/ミューテータ | バリデーション、計算プロパティ |

**重要**: プロパティに直接アクセスしても、Eloquentのマジックメソッドが内部で呼ばれるため、セキュリティは確保されています。明示的なgetter/setterは、特別な処理が必要な場合のみ使用してください。

---

## 変数・パラメータの命名規則

### ID変数の命名ルール

**重要: IDを表す変数は必ずデータソースのプレフィックスを含める**

複数のデータベースを持つアーキテクチャでは、IDの由来を明確にすることが極めて重要です。

#### マスターデータID（mst database）

マスターデータのIDには `mst` プレフィックスを付ける：

```php
// ✅ Good: データソースが明確
$mstUnitId = 'unit_fire_001';
$mstItemId = 'item_potion_hp';
$mstEquipmentId = 'equipment_sword_001';
$mstProductId = 'product_diamond_100';

// ❌ Bad: 曖昧で、トランザクションIDと区別できない
$unitId = 'unit_fire_001';  // これはマスターID？トランザクションID？
$itemId = 'item_potion_hp';
$equipmentId = 'equipment_sword_001';
```

#### トランザクションデータID（trx1/trx2 database）

プレイヤー所有データのIDには `trx` プレフィックスを付ける：

```php
// ✅ Good: プレイヤー所有データであることが明確
$trxUnitId = 123;      // trx_unit.id（プレイヤーが所有するユニットのID）
$trxItemId = 456;      // trx_item.id（プレイヤーが所有するアイテムのID）
$trxEquipmentId = 789; // trx_equipment.id（プレイヤーが所有する装備のID）

// ❌ Bad: マスターIDと混同される
$unitId = 123;      // これはマスターID？トランザクションID？
$itemId = 456;
$equipmentId = 789;
```

#### システムデータID（sys database）

システムデータのIDには `sys` プレフィックスを付ける：

```php
// ✅ Good: システムデータであることが明確
$sysPlayerId = 1001;
$sysPlayerDeviceId = 2002;
$sysDeployId = 50;

// ❌ Bad: 他のプレイヤーIDと混同される可能性
$playerId = 1001;  // これはsys? trx? どのテーブル？
$deviceId = 2002;
```

### テーブルIDカラムの命名ルール

**重要: 各テーブルの`id`カラムは、API パラメータ・変数名として `{テーブル名}_id` 形式で扱う**

#### 2層アプローチ: データベース層 vs API層

このプロジェクトでは、データベース層とAPI層で異なる命名規則を採用しています：

| 層 | 主キーカラム名 | 理由 |
|----|--------------|------|
| **データベース層**（マイグレーション、モデル） | `id` | Laravel標準に従う、Eloquentの機能をフルに活用 |
| **API層**（Request、Response、変数） | `{テーブル名}_id` | どのテーブルのIDか明確、可読性向上 |

**データベーススキーマ:**
```sql
-- ✅ データベース層: idを使用（Laravel標準）
CREATE TABLE sys_friend_apply (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,  -- ✅ id
    sender_sys_player_id BIGINT UNSIGNED NOT NULL,
    receiver_sys_player_id BIGINT UNSIGNED NOT NULL,
    -- ...
);

CREATE TABLE trx_unit (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,  -- ✅ id
    sys_player_id BIGINT UNSIGNED NOT NULL,
    mst_unit_id VARCHAR(64) NOT NULL,
    -- ...
);
```

**Eloquent モデル:**
```php
// ✅ データベース層: $primaryKey の明示は不要（デフォルトで'id'）
class SysFriendApply extends Model
{
    // $primaryKey = 'id';  // ← 不要（デフォルト）
    
    protected $fillable = [
        'sender_sys_player_id',
        'receiver_sys_player_id',
        'status',
    ];
}

// モデル内部では id でアクセス
$sysFriendApply = SysFriendApply::find(123);
$id = $sysFriendApply->id;  // ✅ モデル内部では id でOK
```

**API層での使用:**
```php
// ✅ API層: {テーブル名}_id を使用（明示的）
// APIリクエスト
POST /friend/apply/accept
{
    "sys_friend_apply_id": 123  // ✅ sys_friend_applyテーブルのid
}

// APIレスポンス
{
    "sys_friend_apply_id": 123,  // ✅ 明示的
    "sender_my_id": "ABC12345",
    "receiver_my_id": "XYZ98765",
    "status": "Applied"
}
```

データベーススキーマでは主キーのカラム名は単に`id`ですが、APIリクエスト/レスポンスや変数名では、テーブル名を含めた明確な命名を使用します。

#### API パラメータでの使用

```php
// ✅ Good: テーブル名を含めた明確なパラメータ名
POST /friend/apply/accept
{
    "sys_friend_apply_id": 123  // ✅ sys_friend_applyテーブルのid
}

POST /unit/levelup
{
    "trx_unit_id": 456,         // ✅ trx_unitテーブルのid
    "mst_item_id": "item_exp_small"
}

// ❌ Bad: 曖昧なパラメータ名
POST /friend/apply/accept
{
    "id": 123,              // ❌ どのテーブルのIDか不明
    "friend_apply_id": 123  // ❌ テーブル名と不一致
}
```

#### Request/Responseクラスでの使用

```php
// ✅ Good: テーブル名を含める
class ApplyAcceptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sys_friend_apply_id' => ['required', 'integer', 'min:1'],  // ✅
        ];
    }

    public function getSysFriendApplyId(): int
    {
        return (int) $this->input('sys_friend_apply_id');  // ✅
    }
}

class ApplySendResponse extends _BaseResponse
{
    public function __construct(
        public readonly int $sysFriendApplyId,  // ✅ プロパティ名も明示的
        public readonly int $senderSysPlayerId,
        public readonly int $receiverSysPlayerId,
        // ...
    ) {}

    public static function fromModel(SysFriendApply $sysFriendApply): self
    {
        return new self(
            sysFriendApplyId: $sysFriendApply->id,  // ✅ モデルのidを取得
            senderSysPlayerId: $sysFriendApply->sender_sys_player_id,
            receiverSysPlayerId: $sysFriendApply->receiver_sys_player_id,
            // ...
        );
    }

    public function toArray(): array
    {
        return [
            'sys_friend_apply_id' => $this->sysFriendApplyId,  // ✅ レスポンスでも明示的
            'sender_sys_player_id' => $this->senderSysPlayerId,
            'receiver_sys_player_id' => $this->receiverSysPlayerId,
            // ...
        ];
    }
}

// ❌ Bad: 曖昧な命名
class ApplyAcceptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],           // ❌ 何のIDか不明
            'apply_id' => ['required', 'integer'],     // ❌ テーブル名と不一致
        ];
    }
}
```

#### 変数名での使用

```php
// ✅ Good: UseCase/Service での変数名
class ApplyAcceptUseCase
{
    public function handle(int $sysPlayerId, int $sysFriendApplyId): Response
    {
        $sysFriendApply = $this->repository->findById($sysFriendApplyId);  // ✅
        // ...
    }
}

class UnitService
{
    public function levelUp(int $trxUnitId, int $trxItemId): array
    {
        $trxUnit = $this->unitRepository->findById($trxUnitId);      // ✅
        $trxItem = $this->itemRepository->findById($trxItemId);      // ✅
        // ...
    }
}

// ❌ Bad: 曖昧な変数名
public function handle(int $playerId, int $applyId)  // ❌ テーブル名が不明
{
    $apply = $this->repository->findById($applyId);  // ❌
}
```

#### データベースとAPI層の対応

| データベーステーブル | データベースカラム名 | APIパラメータ名 | 変数名 | 説明 |
|------------------|------------------|--------------|-------|------|
| `sys_friend_apply` | `id` | `sys_friend_apply_id` | `$sysFriendApplyId` | フレンド申請ID |
| `trx_unit` | `id` | `trx_unit_id` | `$trxUnitId` | プレイヤー所有ユニットID |
| `trx_equipment` | `id` | `trx_equipment_id` | `$trxEquipmentId` | プレイヤー所有装備ID |
| `mst_item` | `id` | `mst_item_id` | `$mstItemId` | マスターアイテムID |

**重要**: データベース定義では`id`を使用し、API層では`{table_name}_id`を使用します。

#### 設計の利点

1. **明確性**: API層でどのテーブルのIDか一目で判別可能
2. **一貫性**: データベース層はLaravel標準、API層は明示的な命名で統一
3. **保守性**: リファクタリング時に誤ったIDを渡すバグを防止
4. **可読性**: APIドキュメントを見ずにパラメータの意味が理解できる
5. **互換性**: Laravel標準の`id`を使用することで、Eloquentの全機能が問題なく動作
6. **開発効率**: `$primaryKey`の設定やリレーション定義で外部キーを明示する必要がない

#### 例外: Eloquent Model内部

Eloquentモデルのインスタンスでは、`id`プロパティに直接アクセス可能：

```php
// ✅ Good: モデル内部では id でアクセス
$sysFriendApply = SysFriendApply::find($sysFriendApplyId);
$id = $sysFriendApply->id;  // ✅ モデル内部なので id でOK

// ✅ Good: ただしレスポンスでは明示的に
return [
    'sys_friend_apply_id' => $sysFriendApply->id,  // ✅ APIレスポンスでは明示的
];
```

### 複合キーの場合

複合主キーの場合も、各カラムにプレフィックスを含める：

```php
// ✅ Good: trx_unit テーブルの複合主キー
function findUnit(int $sysPlayerId, int $trxUnitId): ?TrxUnit
{
    return TrxUnit::where('sys_player_id', $sysPlayerId)
                  ->where('id', $trxUnitId)
                  ->first();
}

// ✅ Good: trx_item テーブルの複合主キー
function findItem(int $sysPlayerId, string $mstItemId): ?TrxItem
{
    return TrxItem::where('sys_player_id', $sysPlayerId)
                  ->where('mst_item_id', $mstItemId)
                  ->first();
}
```

### 例外ルール

以下の場合はプレフィックス省略可能：

1. **スコープが明確な場合**
   ```php
   // メソッド内でMstUnitを扱っていることが明白
   public function getMasterUnit(string $mstUnitId): ?MstUnit
   {
       $unit = MstUnit::find($mstUnitId);  // ← $mstUnitId として受け取る
       return $unit;
   }
   ```

2. **ローカル変数（短いスコープ）**
   ```php
   // ループ内の一時変数
   foreach ($mstUnits as $unit) {  // ← $mstUnit より $unit の方が読みやすい
       echo $unit->name;
   }
   ```

3. **複数のデータソースが混在しない場合**
   ```php
   // MstItemRepositoryのメソッド（マスターデータのみ扱う）
   public function findById(string $id): ?MstItem
   {
       return $this->modelClass::find($id);  // ← 明らかにマスターIDなのでOK
   }
   ```

### 実装例

#### UseCase での使用

```php
class UnitLevelUpUseCase
{
    public function handle(
        int $sysPlayerId,      // ✅ sys_player.id
        int $trxUnitId,        // ✅ trx_unit.id（プレイヤー所有）
        string $mstItemId,     // ✅ mst_item.id（マスター定義）
        int $useCount
    ): array {
        // トランザクションデータ取得
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        
        // マスターデータ取得
        $mstUnit = $this->mstUnitRepository->selectById($trxUnit->mst_unit_id);
        $mstItem = $this->mstItemRepository->selectById($mstItemId);
        
        // ...
    }
}
```

#### Service での使用

```php
class UnitLevelService
{
    public function addExp(int $trxUnitId, int $exp): array  // ✅ trx_unit.id
    {
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        $mstUnit = $this->mstUnitRepository->selectById($trxUnit->mst_unit_id);
        
        // レベルアップ処理...
    }
    
    public function getMasterUnitStats(string $mstUnitId): array  // ✅ mst_unit.id
    {
        $mstUnit = $this->mstUnitRepository->selectById($mstUnitId);
        // ...
    }
}
```

#### Repository での使用

```php
class TrxUnitRepository extends _BaseTrxRepository
{
    public function findById(int $trxUnitId): ?TrxUnit  // ✅ 明確
    {
        return $this->getModel($trxUnitId);
    }
    
    public function findByPlayerAndMaster(
        int $sysPlayerId,      // ✅ sys_player.id
        string $mstUnitId      // ✅ mst_unit.id
    ): ?TrxUnit {
        return TrxUnit::where('sys_player_id', $sysPlayerId)
                      ->where('mst_unit_id', $mstUnitId)
                      ->first();
    }
}
```

### 設計の利点

1. **バグの防止**
   ```php
   // ❌ Bad: 誤ったIDを渡してもコンパイルエラーにならない
   function process($unitId) {
       $mstUnit = MstUnit::find($unitId);  // trxUnitIdを渡してもエラーにならない！
   }
   
   // ✅ Good: 変数名で意図が明確、レビュー時に気づきやすい
   function process(int $trxUnitId) {
       $mstUnit = MstUnit::find($trxUnitId);  // ← レビューで即座に誤りに気づける
   }
   ```

2. **コードの可読性向上**
   - 変数名を見るだけでデータソースが分かる
   - ドキュメントを読まなくても理解できる
   - IDEの補完で適切なIDを選びやすい

3. **保守性の向上**
   - リファクタリング時に誤った変数を使うリスクが減る
   - 新規メンバーがコードを理解しやすい
   - データフロー追跡が容易

4. **デバッグの効率化**
   - エラーログから即座にどのテーブルのIDか特定可能
   - クエリの誤りを早期に発見できる

---

## Bool値の命名規約

**Boolean値を表す変数・プロパティ・カラムには、必ず意味が明確になる接頭辞を使用します。**

### 基本ルール

| 接頭辞 | 用途 | 例 |
|--------|------|-----|
| `is_*` | 状態・属性 | `is_leveled_up`, `is_active`, `is_valid`, `is_verified` |
| `has_*` | 所有・存在 | `has_rewards`, `has_expired`, `has_permission` |
| `can_*` | 可能性・権限 | `can_purchase`, `can_upgrade`, `can_access` |
| `needs_*` | 必要性 | `needs_update`, `needs_approval`, `needs_maintenance` |

### 実装例

**✅ Good: 明確な接頭辞を使用**

```php
// Response
class UnitLevelUpResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $isLeveledUp,        // ✅ is_ プレフィックス
        public readonly int $beforeLevel,
        public readonly int $afterLevel,
    ) {}
}

// Service
class UnitLevelService
{
    public function addExp(int $trxUnitId, int $exp): array
    {
        // ...
        return [
            'is_leveled_up' => $isLeveledUp,      // ✅ is_ プレフィックス
            'before_level' => $beforeLevel,
            'after_level' => $afterLevel,
        ];
    }
}

// DTO
class PurchaseValidation
{
    public function __construct(
        public readonly bool $canPurchase,        // ✅ can_ プレフィックス
        public readonly bool $hasEnoughBalance,   // ✅ has_ プレフィックス
        public readonly bool $needsApproval,      // ✅ needs_ プレフィックス
    ) {}
}
```

**❌ Bad: 接頭辞なし、意味が曖昧**

```php
// ❌ Bad: 接頭辞がない
class UnitLevelUpResponse
{
    public function __construct(
        public readonly bool $leveledUp,      // ❌ is_leveled_up とすべき
        public readonly bool $success,        // ❌ 何が成功？
        public readonly bool $valid,          // ❌ 何が有効？
    ) {}
}

// ❌ Bad: 戻り値の配列キーも曖昧
return [
    'leveled_up' => true,   // ❌ is_leveled_up とすべき
    'active' => true,       // ❌ is_active とすべき
    'expired' => false,     // ❌ has_expired とすべき
];
```

### 設計の利点

1. **可読性の向上**
   - コードを読んだ瞬間にBoolean値であることが分かる
   - 意図が明確になり、バグを減らせる

2. **自己文書化**
   - `$isActive` → アクティブかどうか
   - `$hasRewards` → 報酬があるかどうか
   - `$canPurchase` → 購入可能かどうか
   - `$needsUpdate` → 更新が必要かどうか

3. **他言語との互換性**
   - JavaScript, TypeScript, C#, Java等の標準的な命名規約と一致
   - APIレスポンスがフロントエンドでそのまま使いやすい

---

## 変更前後（before/after）の命名規約

**状態の変更を表す変数には、必ず `before_*` / `after_*` の接頭辞を使用します。**

### 基本ルール

| 用途 | 使用する接頭辞 | 避けるべき接頭辞 | 理由 |
|------|--------------|----------------|------|
| 変更前の値 | `before_*` | `old_*`, `previous_*` | ログテーブルの標準カラム名と統一 |
| 変更後の値 | `after_*` | `new_*`, `current_*` | 時系列が明確、before/afterで対になる |

### 実装例

**✅ Good: before/after を使用**

```php
// Response
class UnitLevelUpResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $isLeveledUp,
        public readonly int $beforeLevel,          // ✅ before_ プレフィックス
        public readonly int $afterLevel,           // ✅ after_ プレフィックス
        public readonly int $beforeMaxStamina,     // ✅ before_ プレフィックス
        public readonly int $afterMaxStamina,      // ✅ after_ プレフィックス
    ) {}
}

// Service
class PlayerLevelService
{
    public function addExp(int $sysPlayerId, int $exp): array
    {
        $beforeLevel = $player->level;
        $beforeMaxStamina = $player->getMaxStamina();
        
        // レベルアップ処理...
        
        return [
            'is_leveled_up' => $isLeveledUp,
            'before_level' => $beforeLevel,        // ✅ before_
            'after_level' => $afterLevel,          // ✅ after_
            'before_max_stamina' => $beforeMaxStamina,  // ✅ before_
            'after_max_stamina' => $afterMaxStamina,    // ✅ after_
        ];
    }
}

// ログテーブルのカラム名と統一
CREATE TABLE log_player_level (
    id BIGINT UNSIGNED PRIMARY KEY,
    sys_player_id INT UNSIGNED NOT NULL,
    before_level INT NOT NULL,             -- ✅ before_
    after_level INT NOT NULL,              -- ✅ after_
    before_exp INT NOT NULL,               -- ✅ before_
    after_exp INT NOT NULL,                -- ✅ after_
    created_at TIMESTAMP NOT NULL
);
```

**❌ Bad: old/new を使用**

```php
// ❌ Bad: old/new は避ける
class UnitLevelUpResponse
{
    public function __construct(
        public readonly bool $isLeveledUp,
        public readonly int $oldLevel,         // ❌ beforeLevel とすべき
        public readonly int $newLevel,         // ❌ afterLevel とすべき
    ) {}
}

// ❌ Bad: ログテーブルとの不整合
return [
    'is_leveled_up' => true,
    'old_level' => 5,                       // ❌ before_level とすべき
    'new_level' => 6,                       // ❌ after_level とすべき
];
```

### 設計の利点

1. **時系列が明確**
   - `before` → `after` の順序が自然に読める
   - `old` / `new` よりも意図が明確

2. **ログテーブルの標準と統一**
   - プロジェクト全体でログ記録の形式が統一される
   - データベース設計とコード実装の一貫性

3. **デバッグの効率化**
   - 変更の追跡が容易
   - ログ出力時に`before_*` / `after_*`で検索しやすい

4. **API設計の明確化**
   - フロントエンドでの使用時に意図が分かりやすい
   - 変更履歴の表示が直感的

### 適用範囲

以下の場合に `before_*` / `after_*` を使用：

- **レベルアップ**: `before_level` / `after_level`
- **経験値**: `before_exp` / `after_exp`
- **通貨残高**: `before_balance` / `after_balance`
- **スタミナ**: `before_stamina` / `after_stamina`
- **ステータス値**: `before_hp` / `after_hp`, `before_attack` / `after_attack`
- **数量**: `before_amount` / `after_amount`, `before_count` / `after_count`

---

## 複数形変数の禁止と型サフィックス規則

**重要: 複数形変数の使用を禁止し、データ型を明確にするためにサフィックスを付与する**

このプロジェクトでは、変数名から型が明確に分かるよう、複数形変数を禁止し、Collection/Arrayサフィックスを使用します。

### 基本原則

複数のデータを扱う変数には、必ず`Collection`または`Array`のサフィックスを付けます。

| データ型 | サフィックス | 使用例 | 説明 |
|---------|------------|--------|------|
| **Eloquent Collection** | `Collection` | `$playerCollection`, `$levelCollection`, `$sysFriendApplyCollection` | Eloquentクエリの結果（`get()`, `all()`など） |
| **Array（通常の配列）** | `Array` | `$contentArray`, `$handlerArray`, `$grantedContentArray` | PHPの配列型データ |
| **単数形オブジェクト** | なし | `$player`, `$level`, `$content` | 単一のモデルインスタンスやオブジェクト |

### ❌ 禁止パターン

複数形変数は可読性を損ない、型が不明瞭になるため禁止：

```php
// ❌ Bad: 複数形変数（型が不明瞭）
$players = Player::all();                    // Collection? Array?
$levels = MstLevel::get();                   // Collection? Array?
$contents = ['item1', 'item2'];              // Collection? Array?
$sysFriendApplies = SysFriendApply::where(...)->get();  // Collection? Array?
$handlers = [$handler1, $handler2];          // Collection? Array?
$grantedContents = [];                       // Collection? Array?

// ❌ Bad: Repository層での例
public function getAllByPlayerId(int $sysPlayerId): Collection
{
    $models = $this->queryOrMemory()         // ❌ 型が不明確
        ->where('sys_player_id', $sysPlayerId)
        ->get();
    return $models;
}

// ❌ Bad: UseCase層での例
public function execute(ListRequest $request): ListResponse
{
    $sysFriendApplies = $this->sysFriendApplyRepository  // ❌ 複数形
        ->getAcceptedFriendsByPlayerId($request->sysPlayerId);
    
    return ListResponse::fromCollection($sysFriendApplies);
}

// ❌ Bad: Response層での例
public static function fromCollection(Collection $sysFriendApplies): self  // ❌ パラメータが複数形
{
    $friends = $sysFriendApplies->map(function ($sysFriendApply) {  // ❌ 複数形
        return [
            'my_id' => $sysFriendApply->friend->sys_player_id,
            'name' => $sysFriendApply->friend->name,
        ];
    })->toArray();
    
    return new self($friends);  // ❌ 複数形
}
```

### ✅ 推奨パターン

型が明確になるサフィックスを使用：

```php
// ✅ Good: Collection サフィックス（Eloquent Collection）
$playerCollection = Player::all();
$levelCollection = MstLevel::get();
$sysFriendApplyCollection = SysFriendApply::where(...)->get();

// ✅ Good: Array サフィックス（通常の配列）
$contentArray = ['item1', 'item2'];
$handlerArray = [$handler1, $handler2];
$grantedContentArray = [];

// ✅ Good: 単数形（単一オブジェクト）
$player = Player::find($id);
$level = MstLevel::first();
$content = new DeliveryContent(...);

// ✅ Good: Repository層での例
public function getAllByPlayerId(int $sysPlayerId): Collection
{
    $modelCollection = $this->queryOrMemory()  // ✅ Collection型が明確
        ->where('sys_player_id', $sysPlayerId)
        ->get();
    return $modelCollection;
}

// ✅ Good: UseCase層での例
public function execute(ListRequest $request): ListResponse
{
    $sysFriendApplyCollection = $this->sysFriendApplyRepository  // ✅ Collection型
        ->getAcceptedFriendsByPlayerId($request->sysPlayerId);
    
    return ListResponse::fromCollection($sysFriendApplyCollection);
}

// ✅ Good: Response層での例
public static function fromCollection(Collection $sysFriendApplyCollection): self  // ✅ Collection型が明確
{
    $friendArray = $sysFriendApplyCollection->map(function ($sysFriendApply) {  // ✅ Array型が明確
        return [
            'my_id' => $sysFriendApply->friend->sys_player_id,
            'name' => $sysFriendApply->friend->name,
        ];
    })->toArray();
    
    return new self($friendArray);  // ✅ Array型が明確
}
```

### 各層での使用例

#### Repository層

```php
// ✅ Good: 戻り値がCollectionの場合
public function getAcceptedFriendsByPlayerId(int $sysPlayerId): Collection
{
    $sysFriendApplyCollection = $this->queryOrMemory()  // ✅ Collection
        ->where(function ($query) use ($sysPlayerId) {
            $query->where('sender_sys_player_id', $sysPlayerId)
                  ->orWhere('receiver_sys_player_id', $sysPlayerId);
        })
        ->where('status', FriendApplyStatus::Accepted)
        ->get();
    
    return $sysFriendApplyCollection;
}

// ✅ Good: 配列を返す場合
public function getUniqueKeys(): array
{
    $keyArray = ['sys_player_id', 'mst_item_id'];  // ✅ Array
    return $keyArray;
}
```

#### Domain層（Services）

```php
// ✅ Good: WalletService での例
public function consume(int $sysPlayerId, string $mstItemId, int $amount): void
{
    $balanceCollection = $this->trxWalletBalanceRepository  // ✅ Collection
        ->getActiveBalances($sysPlayerId, $mstItemId);
    
    $expiredBalanceCollection = $balanceCollection->filter(  // ✅ Collection
        fn($balance) => $balance->isExpired()
    );
    
    // ...
}

// ✅ Good: DeliveryService での例
public function deliver(int $sysPlayerId, array $deliveryContents): DeliveryResult
{
    $handlerArray = [  // ✅ Array（ハンドラーの配列）
        ContentType::Diamond => new DiamondHandler(),
        ContentType::Item => new ItemHandler(),
    ];
    
    $successContentArray = [];  // ✅ Array
    $failedContentArray = [];   // ✅ Array
    
    foreach ($deliveryContents as $content) {
        $handler = $handlerArray[$content->type] ?? null;
        // ...
    }
    
    return new DeliveryResult(
        deliveredItemArray: $successContentArray,  // ✅ Array
        failedItemArray: $failedContentArray,      // ✅ Array
        totalCount: count($deliveryContents),
        successCount: count($successContentArray),
        failedCount: count($failedContentArray)
    );
}
```

#### DTO/Value Objectでの使用

```php
// ✅ Good: readonly classのプロパティ
readonly class DeliveryResult
{
    public function __construct(
        public array $deliveredItemArray,  // ✅ Arrayサフィックス
        public array $failedItemArray,     // ✅ Arrayサフィックス
        public int   $totalCount,
        public int   $successCount,
        public int   $failedCount,
    ) {}
    
    public static function success(array $itemArray): self  // ✅ パラメータもArray
    {
        return new self(
            deliveredItemArray: $itemArray,  // ✅ 名前付き引数でArray型が明確
            failedItemArray: [],
            totalCount: count($itemArray),
            successCount: count($itemArray),
            failedCount: 0,
        );
    }
    
    public static function partial(array $deliveredArray, array $failedArray): self
    {
        return new self(
            deliveredItemArray: $deliveredArray,  // ✅ Array型が明確
            failedItemArray: $failedArray,        // ✅ Array型が明確
            totalCount: count($deliveredArray) + count($failedArray),
            successCount: count($deliveredArray),
            failedCount: count($failedArray),
        );
    }
}
```

### 特殊なケース

#### 予約語・専用用語

- **`item`** は `mst_item` テーブル用に予約
- 配送コンテンツなどは `content` を使用（`$contentArray`, `$deliveredContentArray` など）

```php
// ✅ Good: itemはmst_item用に予約
$itemCollection = MstItem::all();  // マスターアイテム
$contentArray = [/* 配送コンテンツ */];  // itemではなくcontentを使用
```

#### 連想配列・マップ構造

key-value形式のマップも`Array`サフィックスを使用：

```php
// ✅ Good: 連想配列にもArrayサフィックス
$originalStateArray = [
    'key1' => ['attr1' => 'value1'],
    'key2' => ['attr2' => 'value2'],
]; // array<string, array<string, mixed>>

$handlerMapArray = [
    ContentType::Diamond => new DiamondHandler(),
    ContentType::Item => new ItemHandler(),
]; // array<ContentType, HandlerInterface>
```

#### ループ変数

foreach内では単数形を使用（サフィックス不要）：

```php
// ✅ Good: Collection → 単数形
foreach ($playerCollection as $player) {  // ✅ $player は単数形
    echo $player->name;
}

// ✅ Good: Array → 単数形
foreach ($contentArray as $content) {  // ✅ $content は単数形
    $this->process($content);
}
```

### 設計の利点

1. **型の明確性**
   - 変数名から即座にCollection/Arrayが判別可能
   - IDEの補完でメソッド候補が適切に表示される

2. **可読性の向上**
   - コードレビュー時に型が一目で分かる
   - ドキュメントを読まなくても理解できる

3. **バグ防止**
   - Collection/Arrayメソッドの混同を防ぐ
   - 型の誤用を早期に発見できる

4. **保守性の向上**
   - 一貫した命名規則で新規メンバーの学習コストを削減
   - リファクタリング時の変更箇所が明確

### チェックリスト

新しいコードを書く際は、以下をチェック：

- [ ] 複数形変数（`$players`, `$items`, `$contents`等）を使用していないか？
- [ ] Eloquent Collectionには`Collection`サフィックスを付けているか？
- [ ] 配列には`Array`サフィックスを付けているか？
- [ ] DTOのプロパティも同じルールに従っているか？
- [ ] メソッドパラメータも同じルールに従っているか？

---

## ディレクトリ命名

### Domain層のディレクトリ構造

```
api/app/Domain/{Domain}/
├── DTOs/              # Data Transfer Objects（DTOサフィックスなし）
├── Services/          # ビジネスロジック（Serviceサフィックス）
├── UseCases/          # アプリケーションロジック（UseCaseサフィックス）
├── Handlers/          # Strategy Pattern実装（Handlerサフィックス）
└── Constants/         # 定数クラス（Constサフィックス）
```

### ディレクトリ命名規則

| ディレクトリ | 命名規則 | 説明 |
|------------|---------|------|
| `DTOs/` | 複数形 | Data Transfer Objects（複数のDTOを格納） |
| `Services/` | 複数形 | ビジネスロジック実装クラス |
| `UseCases/` | 複数形 | ユースケース実装クラス |
| `Handlers/` | 複数形 | Strategy Pattern実装クラス |
| `Constants/` | 複数形 | 定数クラス |
| `Utilities/` | 複数形 | ユーティリティクラス |

**✅ Good: 複数形を使用**

```
app/Domain/Delivery/DTOs/
app/Domain/Auth/DTOs/
```

**❌ Bad: 単数形（古い命名規則）**

```
app/Domain/Delivery/Data/  // ❌ 古い命名規則
app/Domain/Auth/DTO/       // ❌ 単数形
```

---

## クラス命名ルール

### 一覧表

| 種類 | ディレクトリ | 命名規則 | 例 | 備考 |
|-----|------------|---------|-----|------|
| **DTO** | `DTOs/` | サフィックスなし | `DeliveryContent`, `AssetUpdate`, `Maintenance` | |
| **Service** | `Services/` | `XXXService` | `DeliveryService`, `WalletService` | |
| **UseCase** | `UseCases/` | `XXXUseCase` | `VersionCheckUseCase` | |
| **Handler** | `Handlers/` | `XXXHandler` | `ItemDeliveryHandler` | |
| **Interface** | `Handlers/` | `XXXInterface` | `DeliveryHandlerInterface` | |
| **Constants** | `Constants/` | `XXXConst` | `UnitConst`, `EquipmentConst`, `ItemConst`, `BillingConst`, `InAppPurchaseConst` | Modelに定数を定義しない |
| **Utility** | `Utilities/` | `XXXUtility` | `ClockUtility`, `RedisUtility` | |
| **Request (HTTP)** | `Http/Requests/` | `XXXRequest` | `VersionCheckRequest` | HTTPリクエスト専用 |
| **Response** | `Http/Responses/` | `XXXResponse` | `VersionCheckResponse` | |
| **Controller** | `Http/Controllers/` | `XXXController` | `AuthController` | |
| **Model (Mst)** | `Models/Mst/` | `Mst{TableName}` | `MstUnit`, `MstItem` | `$fillable`で`deploy_key`を最初に配置 |
| **Model (その他)** | `Models/{Db}/` | `{Db}{TableName}` | `SysPlayer`, `TrxPlayer`, `SysFriendApply` | `Request`はHTTP専用のため使用不可 |
| **Repository** | `Repositories/` | `XXXRepository` | `SysPlayerRepository` | |

### サフィックスの使い分け

**サフィックスを付ける理由:**
- クラスの役割を明確にする
- 名前空間だけでは区別しづらい場合がある
- IDEの補完で候補を絞り込みやすくする

**サフィックスを付けない理由（DTOのみ）:**
- ディレクトリ（`DTOs/`）でDTOであることが明示的
- サフィックスを付けると冗長になる（`DTOs/DeliveryContentDTO.php`）
- シンプルで読みやすいクラス名を維持

---

## 重要な命名ルール

### `Request`はHTTPリクエスト専用

**重要: `Request`という単語はHTTPリクエストクラス専用です。**

- ✅ **HTTPリクエスト**: `XXXRequest`（例: `VersionCheckRequest`, `SignInRequest`）
- ❌ **データベーステーブル名**: `Request`を使用しない

**理由:**
- Laravelでは`Request`クラスがHTTPリクエストを表す標準的な命名
- `Request`をテーブル名に使うと、HTTPリクエストと混同される
- コードの可読性が低下し、チーム全体での認識が一致しない

**正しい命名:**

| 機能 | ❌ 悪い例 | ✅ 良い例 | 理由 |
|------|----------|----------|------|
| フレンド申請 | `SysFriendRequest` | `SysFriendApply` | Requestと混同しない |
| ギルド参加申請 | `SysGuildRequest` | `SysGuildApply` | Requestと混同しない |
| ログインリクエスト | `SignInApply` | `SignInRequest` | HTTPリクエストなので正しい |

**具体例:**

```php
// ✅ Good: HTTPリクエスト
class SignInRequest extends FormRequest
{
    // HTTPリクエストのバリデーションルール
}

// ✅ Good: フレンド申請のモデル
class SysFriendApply extends Model
{
    protected $table = 'sys_friend_apply';
}

// ❌ Bad: フレンド申請のモデル（HTTPリクエストと混同）
class SysFriendRequest extends Model
{
    protected $table = 'sys_friend_request';  // NG
}
```

---

## DTOクラスの命名

### 基本ルール

**✅ Good: DTOsディレクトリに配置、サフィックスなし**

```php
// ファイルパス: app/Domain/Delivery/DTOs/DeliveryContent.php
namespace App\Domain\Delivery\DTOs;

class DeliveryContent  // ← サフィックスなし
{
    public function __construct(
        public readonly string $resourceType,
        public readonly string $resourceId,
        public readonly int $quantity,
    ) {}
}
```

```php
// ファイルパス: app/Domain/Auth/DTOs/AssetUpdate.php
namespace App\Domain\Auth\DTOs;

class AssetUpdate  // ← サフィックスなし
{
    public function __construct(
        public readonly int $deployAssetId,
        public readonly string $hash,
    ) {}
}
```

**❌ Bad: DataサフィックスやDTOサフィックスを付ける**

```php
// ❌ Bad: Dataサフィックス
class DeliveryContentData { }  // 冗長

// ❌ Bad: DTOサフィックス
class DeliveryContentDTO { }  // 冗長

// ❌ Bad: Dataディレクトリに配置
namespace App\Domain\Delivery\Data;  // 古い命名規則
```

### 理由

1. **ディレクトリで明示**: `DTOs/`ディレクトリに配置することで、DTOであることが明示的
2. **冗長性の回避**: サフィックスを付けると冗長になる（`DTOs/DeliveryContentDTO.php`など）
3. **シンプルで読みやすい**: クラス名が短く、コードが読みやすくなる

---

## Responseクラスの命名

### 特別なルール

Responseクラスは`Http/Responses/`に配置し、DTOとは区別します：

```php
// ファイルパス: app/Http/Responses/Auth/VersionResponse.php
namespace App\Http\Responses\Auth;

use App\Domain\Auth\DTOs\AssetUpdate;  // ← DTOを使用

class VersionResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly ?AssetUpdate $asset = null,  // ← DTOを使用
    ) {}
    
    public function toJsonResponse(): JsonResponse
    {
        return response()->json($this->toArray());
    }
}
```

### ルール

- Responseクラスは`Http/Responses/`に配置
- Responseクラス名には`Response`サフィックスを付ける
- Responseクラス内でDTOを使用可能
- `toJsonResponse()`メソッドを実装

---

## 適用例

### Delivery機能の例

```
app/Domain/Delivery/
├── DTOs/
│   ├── DeliveryContent.php      # ← サフィックスなし
│   └── DeliveryResult.php        # ← サフィックスなし
├── Services/
│   └── DeliveryService.php       # ← Serviceサフィックス
├── UseCases/
│   └── DeliveryUseCase.php       # ← UseCaseサフィックス
└── Handlers/
    ├── DeliveryHandlerInterface.php  # ← Interfaceサフィックス
    ├── ItemDeliveryHandler.php       # ← Handlerサフィックス
    ├── UnitDeliveryHandler.php       # ← Handlerサフィックス
    ├── DiamondDeliveryHandler.php    # ← Handlerサフィックス
    └── WalletDeliveryHandler.php     # ← Handlerサフィックス
```

### 使用例

```php
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\DTOs\DeliveryResult;
use App\Domain\Delivery\Services\DeliveryService;

// DTOの作成（サフィックスなしで読みやす���）
$content = new DeliveryContent(
    resourceType: 'item',
    resourceId: 'item_001',
    quantity: 10
);

// Serviceの使用
$service = app(DeliveryService::class);
$result = $service->delivers($playerId, [$content]);
```

### Auth機能の例

```
app/Domain/Auth/
├── DTOs/
│   ├── AssetUpdate.php          # ← サフィックスなし
│   ├── Maintenance.php          # ← サフィックスなし
│   └── MasterUpdate.php         # ← サフィックスなし
├── Services/
│   └── VersionCheckService.php  # ← Serviceサフィックス
└── UseCases/
    └── VersionCheckUseCase.php  # ← UseCaseサフィックス

app/Http/Responses/Auth/
└── VersionResponse.php          # ← Responseサフィックス
```

### 使用例

```php
use App\Domain\Auth\DTOs\AssetUpdate;
use App\Domain\Auth\DTOs\MasterUpdate;
use App\Http\Responses\Auth\VersionResponse;

// DTOの作成
$assetUpdate = new AssetUpdate(
    deployAssetId: 123,
    hash: 'abc123...'
);

$masterUpdate = new MasterUpdate(
    deployMasterId: 456,
    hash: 'def456...'
);

// Responseの作成
$response = new VersionResponse(
    needsUpdate: true,
    asset: $assetUpdate,
    master: $masterUpdate
);

return $response->toJsonResponse();
```

---

## Modelの命名規則

### データベース接頭辞

| データベース | テーブル名 | モデルクラス名 | ディレクトリ |
|------------|-----------|--------------|-------------|
| sys | `sys_player` | `SysPlayer` | `App\Models\Sys\` |
| mst | `mst_item` | `MstItem` | `App\Models\Mst\` |
| trx | `trx_player` | `TrxPlayer` | `App\Models\Trx\` |
| log | `log_gacha` | `LogGacha` | `App\Models\Log\` |
| adm | `adm_account` | `AdmAccount` | `App\Models\Adm\` |
| tol | `tol_banner` | `TolBanner` | `App\Models\Tol\` |

### ルール

- テーブル名の接頭辞をPascalCaseに変換
- 例: `sys_player` → `SysPlayer`
- 例: `mst_item_category` → `MstItemCategory`

### Mst Model の特別なルール

**重要: `$fillable` の配列順序**

Mstモデルでは、**`$fillable` 配列の最初に必ず `deploy_key` を配置**します。

**✅ Good: deploy_keyが最初**

```php
// app/Models/Mst/MstUnit.php
namespace App\Models\Mst;

class MstUnit extends _BaseMst
{
    protected $fillable = [
        'deploy_key',  // ← 必ず最初
        'id',
        'type',
        'element',
        'rarity',
        'grade',
        'hp',
        'attack',
        'defense',
        'speed',
    ];
}
```

```php
// app/Models/Mst/MstPlayerLevel.php
namespace App\Models\Mst;

class MstPlayerLevel extends _BaseMst
{
    protected $fillable = [
        'deploy_key',  // ← 必ず最初
        'level',
        'required_exp',
        'max_stamina',
    ];
}
```

**❌ Bad: deploy_keyが最初ではない**

```php
class MstUnit extends _BaseMst
{
    protected $fillable = [
        'id',
        'deploy_key',  // ← 最初ではない
        'type',
        // ...
    ];
}
```

**理由:**
- **一貫性**: 全てのMstモデルで`deploy_key`の位置が統一され、コードの可読性が向上
- **バージョン管理の明確化**: マスターデータのバージョン管理カラムであることが視覚的に明確
- **新規モデル追加時の迷いを排除**: 規約が明確なため、実装時に判断不要

### ローカライズテーブル (L10n) の重要なルール

**重要: ローカライズテーブル（`*_l10n`）にはModelクラスを作成しない**

- マスターデータのJSON配信時に、メインテーブルとL10nテーブルをJOINしてJSON化する
- ローカライズデータは単独で扱うことがなく、常にメインテーブルと一緒に使用される

**❌ Bad: L10nモデルを作成**

```php
// app/Models/Mst/MstUnitL10n.php  ← 作成不要
// app/Models/Mst/MstItemL10n.php  ← 作成不要
// app/Models/Mst/MstEquipmentL10n.php  ← 作成不要
```

**✅ Good: Repositoryで直接クエリ**

```php
// app/Repositories/Mst/MstUnitRepository.php
class MstUnitRepository extends _BaseMstRepository
{
    public function selectAllWithL10n(string $locale): Collection
    {
        return DB::connection('mst')
            ->table('mst_unit')
            ->leftJoin('mst_unit_l10n', function ($join) use ($locale) {
                $join->on('mst_unit.id', '=', 'mst_unit_l10n.unit_id')
                     ->where('mst_unit_l10n.locale', '=', $locale);
            })
            ->select('mst_unit.*', 'mst_unit_l10n.name', 'mst_unit_l10n.description')
            ->get();
    }
}
```

**理由:**
- **不要なファイル削減**: L10nモデルは単独で使用されないため、Modelクラスは不要
- **保守性向上**: Repositoryで直接クエリを実装すれば十分
- **シンプルな設計**: メインテーブルと常に一緒に使用されるため、分離する必要がない

### Constants（定数）の配置

**重要: Modelクラスに定数を直接定義しない**

ビジネスドメインに関連する定数は、Modelクラスではなく、Domainレイヤーの `Constants` ディレクトリに配置します。

**✅ Good: Constantsクラスに分離**

```php
// app/Domain/Unit/Constants/UnitConst.php
namespace App\Domain\Unit\Constants;

class UnitConst
{
    // ユニットタイプ
    const TYPE_ATTACK = 'Attack';
    const TYPE_DEFENSE = 'Defense';
    const TYPE_SUPPORT = 'Support';

    // ユニット属性
    const ELEMENT_FIRE = 'Fire';
    const ELEMENT_WATER = 'Water';
    const ELEMENT_WIND = 'Wind';
    const ELEMENT_EARTH = 'Earth';
    const ELEMENT_LIGHT = 'Light';
    const ELEMENT_DARK = 'Dark';

    // ユニットレアリティ
    const RARITY_UR = 'UR';
    const RARITY_SSR = 'SSR';
    const RARITY_SR = 'SR';
    const RARITY_R = 'R';
    const RARITY_UC = 'UC';
    const RARITY_C = 'C';

    // ヘルパーメソッド
    public static function allTypes(): array
    {
        return [self::TYPE_ATTACK, self::TYPE_DEFENSE, self::TYPE_SUPPORT];
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::allTypes(), true);
    }
}
```

```php
// app/Models/Mst/MstUnit.php（定数なし）
namespace App\Models\Mst;

class MstUnit extends _BaseMst
{
    protected $fillable = [
        'deploy_key',
        'id',
        'type',
        'element',
        'rarity',
        // ...
    ];
    
    // 定数はなし。リレーションのみ定義
    public function l10n(): HasMany
    {
        return $this->hasMany(MstUnitL10n::class, 'mst_unit_id', 'id');
    }
}
```

**❌ Bad: Modelに定数を直接定義**

```php
// ❌ 避けるべき実装
class MstUnit extends _BaseMst
{
    const TYPE_ATTACK = 'Attack';
    const TYPE_DEFENSE = 'Defense';
    const ELEMENT_FIRE = 'Fire';
    const RARITY_UR = 'UR';
    // ...
}
```

**理由:**
- **関心の分離**: Modelはデータ構造のみに集中すべき
- **再利用性**: 定数を他のサービスやユースケースから簡単に参照可能
- **バリデーション機能**: Constantsクラスに `isValid*()` メソッドで値の検証が容易
- **DDD準拠**: ドメイン知識をDomainレイヤーに集約

---

## Repositoryの命名規則

### メソッド命名規則

| 用途 | メソッド名 | 戻り値 | 例 |
|-----|-----------|-------|-----|
| ID検索 | `selectById(int $id)` | 単一モデル | `selectById(123)` |
| 単一レコード検索 | `selectByXxx()` | 単一モデル | `selectByMyId('player_001')` |
| 複数レコード検索 | `selectListByXxx()` | コレクション | `selectListByStatus('active')` |
| データ挿入 | `insert()` | モデル | `insert($data)` |
| データ更新 | `update()` | モデル | `update($model)` |
| データ削除 | `delete()` | bool | `delete($model)` |

### 実装例

```php
class SysPlayerRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayer::class;
    protected string $cachePrefix = 'sys:player';

    // 単一レコード検索
    public function selectByMyId(string $myId): ?SysPlayer
    {
        return $this->cacheRemember(
            "my_id:{$myId}",
            fn() => $this->newQuery()->where('my_id', $myId)->first()
        );
    }

    // 単一レコード検索
    public function selectByUuid(string $uuid): ?SysPlayer
    {
        return $this->cacheRemember(
            "uuid:{$uuid}",
            fn() => $this->newQuery()->where('uuid', $uuid)->first()
        );
    }

    // 複数レコード検索
    public function selectListByShardId(int $shardId): Collection
    {
        return $this->cacheRemember(
            "shard_id:{$shardId}",
            fn() => $this->newQuery()->where('shard_id', $shardId)->get()
        );
    }
}
```

---

## 定数クラスの命名規則

### ルール

- クラス名: `XXXConst`（Constサフィックス）
- 定数名: `UPPER_SNAKE_CASE`
- ディレクトリ: `Domain/{Domain}/Constants/`

### 実装例

```php
// ファイルパス: app/Domain/Wallet/Constants/WalletConst.php
namespace App\Domain\Wallet\Constants;

class WalletConst
{
    // 通貨タイプ
    public const CURRENCY_TYPE_GOLD = 'gold';
    public const CURRENCY_TYPE_DIAMOND = 'diamond';
    public const CURRENCY_TYPE_FRIEND_POINT = 'friend_point';

    // 最大値
    public const MAX_GOLD = 999_999_999;
    public const MAX_DIAMOND = 999_999;
    public const MAX_FRIEND_POINT = 999_999;
}
```

```php
// ファイルパス: app/Domain/Item/Constants/ItemConst.php
namespace App\Domain\Item\Constants;

class ItemConst
{
    // アイテムタイプ
    const TYPE_CONSUMABLE = 'Consumable';
    const TYPE_MATERIAL = 'Material';
    const TYPE_EQUIPMENT_ENHANCEMENT = 'EquipmentEnhancement';
    const TYPE_UNIT_ENHANCEMENT = 'UnitEnhancement';

    // アイテム効果
    const EFFECT_HP_RECOVERY = 'HPRecovery';
    const EFFECT_STAMINA_RECOVERY = 'StaminaRecovery';
    const EFFECT_EXP_BOOST = 'ExpBoost';
    const EFFECT_UNIT_EXP = 'UnitExp';
    
    public static function allTypes(): array { /* ... */ }
    public static function isValidType(string $type): bool { /* ... */ }
}
```

```php
// ファイルパス: app/Domain/Billing/Constants/BillingConst.php
namespace App\Domain\Billing\Constants;

class BillingConst
{
    // 決済プラットフォーム
    const PLATFORM_APP_STORE = 'AppStore';
    const PLATFORM_GOOGLE_PLAY = 'GooglePlay';
    const PLATFORM_PAYPAL = 'PayPal';
    const PLATFORM_STRIPE = 'Stripe';

    // プラットフォーム商品種別
    const PRODUCT_TYPE_CONSUMABLE = 'Consumable';
    const PRODUCT_TYPE_NON_CONSUMABLE = 'NonConsumable';
    const PRODUCT_TYPE_SUBSCRIPTION = 'Subscription';
    
    public static function getAllPlatforms(): array { /* ... */ }
    public static function isValidPlatform(string $platform): bool { /* ... */ }
}
```

```php
// ファイルパス: app/Domain/InAppPurchase/Constants/InAppPurchaseConst.php
namespace App\Domain\InAppPurchase\Constants;

class InAppPurchaseConst
{
    // 課金商品タイプ
    const TYPE_DIAMOND = 'Diamond';
    const TYPE_PACK = 'Pack';
    const TYPE_PASS = 'Pass';

    // 購入制限リセット
    const PURCHASE_LIMIT_RESET_NONE = 'None';
    const PURCHASE_LIMIT_RESET_DAILY = 'Daily';
    const PURCHASE_LIMIT_RESET_WEEKLY = 'Weekly';
    const PURCHASE_LIMIT_RESET_MONTHLY = 'Monthly';

    // コンテンツタイプ
    const CONTENT_TYPE_ITEM = 'Item';
    const CONTENT_TYPE_UNIT = 'Unit';
    const CONTENT_TYPE_FREE_DIAMOND = 'FreeDiamond';
    
    public static function allTypes(): array { /* ... */ }
    public static function isValidType(string $type): bool { /* ... */ }
}
```

### 使用例

```php
use App\Domain\Wallet\Constants\WalletConst;

if ($currencyType === WalletConst::CURRENCY_TYPE_GOLD) {
    // ゴールド処理
}
```

---

## Utilityクラスの命名規則

### ルール

- クラス名: `XXXUtility`（Utilityサフィックス）
- メソッド: `static`メソッド
- ディレクトリ: `app/Utilities/`

### 実装例

```php
// ファイルパス: app/Utilities/ClockUtility.php
namespace App\Utilities;

use Carbon\CarbonImmutable;

class ClockUtility
{
    private static ?CarbonImmutable $fixedNow = null;

    public static function now(): CarbonImmutable
    {
        return self::$fixedNow ?? CarbonImmutable::now();
    }

    public static function fixNow(CarbonImmutable $now): void
    {
        self::$fixedNow = $now;
    }

    public static function initialize(): void
    {
        self::$fixedNow = null;
    }
}
```

---

## 既存コードのリファクタリング

### プロジェクト全体で命名規約を統一済み

✅ **完了した変更:**

1. `Domain/Delivery/Data/` → `Domain/Delivery/DTOs/` に変更完了
2. `Domain/Auth/Data/` → `Domain/Auth/DTOs/` に変更完了
3. `AssetUpdateData` → `AssetUpdate` に変更完了
4. `MaintenanceData` → `Maintenance` に変更完了
5. `MasterUpdateData` → `MasterUpdate` に変更完了
6. `DeliveryItem` → `DeliveryContent` に変更完了

### 新規コード作成時の注意

**必ずこの命名規約に従う:**

- ✅ DTOクラスにはサフィックスを付けない
- ✅ ディレクトリは`DTOs`（複数形）を使用
- ✅ Serviceクラスには`Service`サフィックス
- ✅ UseCaseクラスには`UseCase`サフィックス
- ✅ Handlerクラスには`Handler`サフィックス
- ✅ Constクラスには`Const`サフィックス
- ✅ Utilityクラスには`Utility`サフィックス

---

## チェックリスト

### 新しいクラスを作成する前に確認

- [ ] 適切なディレクトリに配置しているか（DTOs/, Services/, 等）
- [ ] クラス名に適切なサフィックスを付けているか（DTOは除く）
- [ ] ディレクトリ名は複数形になっているか
- [ ] 命名規約に従っているか（一覧表を参照）
- [ ] 既存のコードと一貫性があるか

### リファクタリング時に確認

- [ ] 古い命名規則（`Data/`, `*Data`等）を使用していないか
- [ ] 全てのimport文を更新したか
- [ ] 型ヒントも更新したか
- [ ] テストコードも更新したか

---

## Serviceメソッドの戻り値における命名規則

**推奨: Serviceメソッドが複数の値を返す場合、配列分割代入（array destructuring）を使用**

ServiceクラスのメソッドがEloquentモデル等の複数の値を返す場合、**順序配列**で返し、呼び出し側で配列分割代入を使用することを推奨します。

### 配列分割代入パターン（推奨）

```php
// ✅ Best: 順序配列 + 配列分割代入
// Service側
/**
 * @return array{SysPlayer, SysPlayerDevice}
 */
public function createPlayer(string $deviceId, ?array $deviceInfo = null): array
{
    $sysPlayer = SysPlayer::create([...]);
    $sysPlayerDevice = SysPlayerDevice::create([...]);

    return [$sysPlayer, $sysPlayerDevice];  // ✅ 順序配列
}

// UseCase側
[$sysPlayer, $sysPlayerDevice] = $this->playerService->createPlayer($deviceId, $deviceInfo);
// ✅ 配列分割代入で直接変数に代入
```

### 配列分割代入の利点

1. **コードが簡潔**: 配列キーの文字列リテラルが不要
2. **タイプセーフ**: PHPDocで型を明示でき、IDEの補完が効く
3. **タイプミスのリスク削減**: 文字列キーのタイプミスがなくなる
4. **可読性向上**: 変数名が明確で、不要な中間変数がない

### 実装例

```php
// ✅ Best: PlayerService.php（配列分割代入パターン）
namespace App\Domain\Auth\Services;

class PlayerService
{
    /**
     * 新しいプレイヤーを作成
     *
     * @param string $deviceId
     * @param array<string, mixed>|null $deviceInfo
     * @return array{SysPlayer, SysPlayerDevice}  // ✅ 順序配列の型を明示
     */
    public function createPlayer(string $deviceId, ?array $deviceInfo = null): array
    {
        $sysPlayer = SysPlayer::create([...]);
        $sysPlayerDevice = SysPlayerDevice::create([...]);

        return [$sysPlayer, $sysPlayerDevice];  // ✅ 順序配列
    }

    /**
     * 既存デバイスからプレイヤーとデバイス情報を取得
     *
     * @param string $deviceId
     * @return array{SysPlayer, SysPlayerDevice}|null
     */
    public function findByDeviceId(string $deviceId): ?array
    {
        $sysPlayerDevice = $this->deviceRepository->selectByDeviceId($deviceId);
        
        if ($sysPlayerDevice === null) {
            return null;
        }

        return [$sysPlayerDevice->player, $sysPlayerDevice];  // ✅ 順序配列
    }
}
```

```php
// ✅ Good: UnitService.php（3つ以上の値を返す場合）
namespace App\Domain\Unit\Services;

class UnitService
{
    /**
     * ユニットをレベルアップ
     *
     * @param int $trxUnitId
     * @param int $exp
     * @return array{TrxUnit, MstUnit, bool}  // ✅ 3つの値を順序配列で返す
     */
    public function addExp(int $trxUnitId, int $exp): array
    {
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        $mstUnit = $this->mstUnitRepository->selectById($trxUnit->mst_unit_id);
        
        $isLeveledUp = false;
        // ... レベルアップ処理
        
        return [$trxUnit, $mstUnit, $isLeveledUp];  // ✅ 順序配列
    }
}
```

### UseCaseでの使用例

```php
// ✅ Best: SignUpUseCase.php（配列分割代入）
class SignUpUseCase
{
    public function handle(string $deviceId, array $deviceInfo): SignUpResponse
    {
        return $this->executeWithTransaction(function () use ($deviceId, $deviceInfo) {
            // ✅ 配列分割代入で直接変数に代入
            [$sysPlayer, $sysPlayerDevice] = $this->playerService->createPlayer($deviceId, $deviceInfo);

            [$dtoToken, $sysPlayerToken] = $this->tokenService->generateDtoToken($sysPlayer, $sysPlayerDevice);

            return new SignUpResponse(
                sysPlayer: $sysPlayer,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $sysPlayerToken,
                dtoToken: $dtoToken,
            );
        });
    }
}

// ❌ Bad: 連想配列 + 中間変数（冗長）
class SignUpUseCase
{
    public function handle(string $deviceId, array $deviceInfo): SignUpResponse
    {
        return $this->executeWithTransaction(function () use ($deviceId, $deviceInfo) {
            $result = $this->playerService->createPlayer($deviceId, $deviceInfo);
            $sysPlayer = $result['sys_player'];              // ❌ 文字列キー
            $sysPlayerDevice = $result['sys_player_device'];  // ❌ 文字列キー

            $tokenData = $this->tokenService->generateDtoToken($sysPlayer, $sysPlayerDevice);

            return new SignUpResponse(
                sysPlayer: $sysPlayer,
                sysPlayerDevice: $sysPlayerDevice,
                sysPlayerToken: $tokenData['model'],  // ❌ 文字列キー
                dtoToken: $tokenData['dto'],          // ❌ 文字列キー
            );
        });
    }
}
```

### 順序配列の戻り値に関する注意点

1. **返す順序は意味のある順序にする**
   - メインのモデル → 関連モデル → フラグ/数値 の順が推奨
   - 例: `[$sysPlayer, $sysPlayerDevice]` （プレイヤー→デバイス）
   - 例: `[$trxUnit, $mstUnit, $isLeveledUp]` （トランザクション→マスター→フラグ）

2. **PHPDocで型を明示**
   - `@return array{Type1, Type2}` 形式で型を明示
   - IDEの補完が効果的に機能
   - 静的解析ツール（PHPStan, Psalm）でのチェックが容易

3. **レガシーコードとの併用**
   - 既存の連想配列パターンを全て書き換える必要はない
   - 新規メソッドや大幅な変更時に配列分割代入パターンを採用
   - 段階的に移行していく

### レガシー：連想配列パターン（非推奨）

配列分割代入が導入される前のパターンです。既存コードではこのパターンが使用されている場合があります。

```php
// ⚠️ Legacy: 連想配列パターン（非推奨、既存コードでのみ使用）
// 配列キーはsnake_caseで統一

// Service側
/**
 * @return array{sys_player: SysPlayer, sys_player_device: SysPlayerDevice}
 */
public function createPlayer(string $deviceId, ?array $deviceInfo = null): array
{
    // ...
    return [
        'sys_player' => $sysPlayer,
        'sys_player_device' => $sysPlayerDevice,
    ];
}

// UseCase側
$result = $this->playerService->createPlayer($deviceId, $deviceInfo);
$sysPlayer = $result['sys_player'];              // ⚠️ 文字列キー
$sysPlayerDevice = $result['sys_player_device'];  // ⚠️ 文字列キー
```

**注意**: 新規コードでは配列分割代入パターンを使用してください。

---

## 関連ドキュメント

- [アーキテクチャ設計](./architecture.md) - ディレクトリ構造の詳細
- [コーディング規約](./coding-standards.md) - 各クラスの実装ルール
- [API設計](./api.md) - Request/Responseの命名規則
