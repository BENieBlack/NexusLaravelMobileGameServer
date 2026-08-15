# コーディング規約

このドキュメントは、本プロジェクトで使用するコーディング規約をまとめたものです。

---

## 目次

1. [メソッド命名規則](#メソッド命名規則)
2. [DTO・ValueObjectの命名](#dtovalueobjectの命名)
3. [削除の規約](#削除の規約)
4. [Adapterの役割](#adapterの役割)
5. [レスポンスの契約](#レスポンスの契約)
6. [Request・Responseクラスの命名規則](#requestresponseクラスの命名規則)
7. [ディレクトリ構成](#ディレクトリ構成)
8. [名前空間とクラスの配置](#名前空間とクラスの配置)
9. [クラスの責務](#クラスの責務)
10. [ファイル命名規則](#ファイル命名規則)
11. [まとめ](#まとめ)

---

## メソッド命名規則

### 基本原則

**`get` はゲッターにのみ使う。** 何をしているかを動詞で表し、DBに触れる箇所が名前から分かるようにする。

### 動詞の使い分け

| 動詞 | 用途 | 例 |
|---|---|---|
| `get` | 自身のプロパティを返すゲッター（引数なし） | `getSysPlayerId()` |
| `select` | DBに直接アクセスして取得する | `selectById()` `selectMaxLevel()` |
| `insert` / `update` / `delete` | DBへの登録・更新・削除 | `insertMember()` `updateLastLoginAt()` |
| `persist` | UnitOfWorkのキューに積む（フラッシュ時に反映） | `persist()` `persistItem()` |
| `softDelete` / `hardDelete` | 論理削除 / 物理削除 | `softDeleteModel()` `hardDeleteModel()` |
| `count` | 件数を数える | `countMembers()` `countUnread()` |
| `find` | 取得済みデータや別レイヤ経由で単一の対象を探す | `findPlayerById()` `findMaxLevel()` |
| `search` | 条件に合う複数件を探す | `searchByName()` |
| `calc` | 計算して求める | `calcExpToNextLevel()` |
| `resolve` | 設定・文脈・引数から値を決定する | `resolveConnectionName()` `resolveRarity()` |
| `build` | 値やオブジェクトを組み立てる | `buildCacheKey()` `buildVersionString()` |
| `fetch` | 外部API・Redisなどから取得する | `fetchSubscriptionStatus()` |
| `all` / `available` | 定数の一覧を返す（静的） | `allTypes()` `availableStatuses()` |

### 注意点

- `save` はEloquentの `Model::save()` を指すため、UnitOfWorkにキューイングする処理には使わない（`persist` を使う）。
- Repositoryは常にModelを返す。DTOへの変換はAdapterやServiceの役務とする。

---

## DTO・ValueObjectの命名

### サフィックスは付けない

役割はディレクトリと名前空間で示す。クラス名にパターン名を繰り返さない。

| ディレクトリ | 用途 | 例 |
|---|---|---|
| `DataTransferObjects/` | データ転送（可変も許容、`toArray()` あり） | `Player` `Guild` `ResourceDeliverySummary` |
| `ValueObjects/` | ドメイン概念（不変、`toArray()` なし） | `VipConfig` `Token` `CurrencyBalance` |

```php
// ✅ 名前空間で役割が分かる
use NexusPlayer\DataTransferObjects\Player;
use NexusVip\ValueObjects\VipConfig;

// ❌ サフィックスの重複
use NexusPlayer\DataTransferObjects\PlayerDto;
```

Eloquent Modelは `Sys` / `Trx` / `Mst` / `Log` の接頭辞を持つため、
`Player`（DTO）と `SysPlayer`（Model）は同一ファイル内でも共存できる。

### ディレクトリ名は略さない

`DTOs` ではなく `DataTransferObjects` とする。`ValueObjects` をはじめ、
このリポジトリのディレクトリ名は全て綴った複数形で統一している。

なお `toDto()` / `toDtoArray()` のような**変換メソッド名**は、
特定のクラスではなくパターンを指すため `Dto` のままでよい。

---

## 削除の規約

### 論理削除と物理削除

削除はどちらもUnitOfWork経由で行う。Eloquentの `$model->delete()` は実行時に拒否される。

| メソッド | 動作 | 積まれるキュー |
|---|---|---|
| `softDeleteModel($model)` | `is_delete = true` を立てる（行は残る） | `modelQueue`（UPDATE） |
| `hardDeleteModel($model)` | 行をDELETEで消す | `deleteQueue`（DELETE） |

いずれも `flush()` のタイミングで実際のクエリが実行される。

### どちらを使うか

- **trx系** … `is_delete` カラムを持つため両方使える。参照を残したい場合は `softDeleteModel()`
- **sys系** … `is_delete` カラムがないため `hardDeleteModel()` のみ

論理削除した行を後からまとめて物理削除する仕組みは持たない。消したい場合は最初から `hardDeleteModel()` を使う。

### タイムスタンプはDBに任せる

`created_at` / `updated_at` はテーブル定義の既定値で採番する。
アプリケーション側では設定しない。

```php
// マイグレーション
$table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
$table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
```

```php
// ❌ アプリ側で設定しない
$model->setAttribute('updated_at', ClockUtility::now());
new TrxStamina(['created_at' => $now, 'updated_at' => $now]);
```

キューに積んだ時点では値が入っておらず、フラッシュ後に `BatchExecutor` が
DB から読み戻してモデルへ反映する。タイムスタンプを参照する処理は
フラッシュ後に行うこと。

なお `system_at` のような**業務上の意味を持つ時刻**はこの限りではなく、
`ClockUtility` 経由でアプリケーション側が設定する。

### 日時は文字列で扱う

`last_login_at` `start_at` などの日時は、DB取得からレスポンスまで
一貫して `Y-m-d H:i:s` の文字列で扱う。Carbonへのキャストを強制しない。

```php
// ✅ 文字列を返す
public function getStartAt(): ?string
{
    return $this->getDateAttributeString('start_at');
}

// ❌ Carbonを返すと呼び出し元がキャストに縛られる
public function getStartAt(): ?CarbonImmutable
```

**比較は文字列のまま行える。** `Y-m-d H:i:s` は固定長なので、
辞書順比較が時系列順比較と一致する。

```php
ClockUtility::isPast($expiresAt);            // 過去かどうか
ClockUtility::isFuture($expiresAt);          // 未来かどうか
ClockUtility::isWithin($startAt, $endAt);    // 期間内かどうか

$latest = max($createdAtList);               // 素の比較も使える
```

日付の加算・差分など**演算が必要な箇所でだけ** `ClockUtility::parse()` で
Carbonに変換する。変換はその場に閉じ込め、境界を越えて持ち回らない。

### Eloquentによる即時書き込みの禁止

`_BaseModel` が `save()` / `update()` / `delete()` / `forceDelete()` を拒否する。トランザクション境界とPITRログの整合性を壊すため。

テストのフィクスチャやSeederなど、UnitOfWorkを介さない投入経路は `_BaseModel::allowDirectWrites()` で明示的に許可する。

---

## Adapterの役割

パッケージ（`packages/nexus-*`）はアプリ層のEloquent Modelに依存できない。
一方Repositoryは常にModelを返す。この境界を翻訳するのがAdapterである。

```
パッケージのService → XxxRepositoryInterface（DTOを要求）
                             ↑ implements
アプリ層              XxxRepositoryAdapter    … 実装と委譲
                             ↓ uses
                      XxxAdapter              … Model↔DTO変換
                             ↓ wraps
                      SysXxxRepository        … Modelを返す
```

### 2種類のAdapterを混同しない

| クラス | 配置 | 役割 |
|---|---|---|
| `*RepositoryAdapter` | `App\Repositories\**` | パッケージInterfaceの実装・委譲。DI対象 |
| `*Adapter` | `App\Adapters\{Domain}\` | Model↔DTOの変換。staticユーティリティ |

### 変換は App\Adapters に集約する

`RepositoryAdapter` の中に private 変換メソッドを書かない。
変換はUseCaseなど他の層からも使うため、切り出しておく。

```php
// ✅ 委譲する
return $model ? GuildAdapter::toDto($model) : null;

// ❌ 内部に閉じ込めると他の層から使えない
private function convertToDto(SysGuild $model): Guild
```

変換クラスは `toDto()` と `toDtoArray()` を持つ。

---

## レスポンスの契約

### UseCase は必ず Response クラスを返す

`_BaseController::execute()` は `_BaseResponseInterface` の実装のみを受け取る。
戻り値の型を固定することで、レスポンスの形が呼び出し方によって変わったり、
意図しないキーが混入したりするのを防ぐ。

```php
// ✅ Responseクラスを返す
public function exec(int $sysPlayerId): GuildLeaveResponse
{
    return $this->executeWithTransaction(function () use ($sysPlayerId) {
        // ...
        return new GuildLeaveResponse($sysPlayerId);
    });
}

// ❌ 配列や void を返す（LogicExceptionになる）
public function exec(int $sysPlayerId): void
```

Responseクラスは `_BaseResponse` を継承し、`toArray()` だけを実装する。
`Responsable` は使わない（JSONの組み立て方が二系統になるため）。

### IDキーはテーブルまで特定できる名前にする

`*_id` は、どのテーブルのどのカラムを指すかが名前から分かるようにする。
`apply_id` のような曖昧な名前は使わない。

| ❌ 曖昧 | ✅ 特定できる | 実体 |
|---|---|---|
| `guild_id` | `sys_guild_id` | `sys_guild.id` |
| `apply_id` | `sys_guild_apply_id` | `sys_guild_apply.id` |
| `member_id` | `sys_guild_member_id` | `sys_guild_member.id` |
| `player_id` | `sys_player_id` | `sys_player.id` |

リクエストのパラメータ名も同じ規則に従う。

カラム名自体に接頭辞が無いもの（`my_id` `content_id` `sender_id` `device_id` など）は、
DBのカラム名がそのまま対応するため据え置く。

### エンベロープは付けない

`toArray()` の内容がそのままトップレベルになる。`data` などでラップしない。

```json
{ "guilds": [ ... ] }
```

---

## Request・Responseクラスの命名規則

### 基本原則

Request・Responseクラスの命名は、**エンドポイントのアクション名に合わせる**ことを原則とします。

### 命名ルール

#### ✅ 正しい命名

エンドポイントが `/auth/version` の場合：

```php
// Request
class VersionRequest extends FormRequest
{
    // ...
}

// Response
class VersionResponse implements Arrayable, JsonSerializable
{
    // ...
}
```

#### ❌ 誤った命名

```php
// NG: アクション名に "Check" が含まれていない
class VersionCheckRequest extends FormRequest
{
    // ...
}

class VersionCheckResponse implements Arrayable, JsonSerializable
{
    // ...
}
```

### 命名例

| エンドポイント | Request | Response |
|---|---|---|
| `POST /auth/sign_up` | `SignUpRequest` | `SignUpResponse` |
| `POST /auth/sign_in` | `SignInRequest` | `SignInResponse` |
| `GET /auth/version` | `VersionRequest` | `VersionResponse` |
| `GET /player/me` | （不要、標準Requestを使用） | `PlayerMeResponse` |
| `POST /gacha/draw` | `DrawRequest` | `DrawResponse` |
| `GET /player/inventory` | `InventoryRequest` | `InventoryResponse` |
| `POST /player/equip` | `EquipRequest` | `EquipResponse` |
| `GET /mission/list` | `ListRequest` | `ListResponse` |
| `POST /mission/claim` | `ClaimRequest` | `ClaimResponse` |

### 理由

- **一貫性**: エンドポイントとクラス名が一致することで、コードの可読性が向上
- **シンプル**: 不要な接頭辞・接尾辞（例: "Check", "Get"）を避けることで、クラス名がシンプルになる
- **保守性**: エンドポイント名からクラス名を推測しやすくなる

---

## ディレクトリ構成

### Request・Responseクラスの配置

Request・Responseクラスは、**機能ごとにサブディレクトリに分けて配置**します。

### Dataクラスの配置

**Domainに紐づくデータ構造（DTO）は、`App\Domain\{Domain}\Data` 名前空間に配置**します。
Responseクラスから参照される共有データ構造は、Dataクラスとして定義します。

#### ディレクトリ構造例

```
app/
├── Domain/
│   └── Auth/
│       ├── Data/                  # DTOクラス
│       │   ├── AssetUpdateData.php
│       │   ├── MaintenanceData.php
│       │   └── MasterUpdateData.php
│       ├── Services/
│       └── UseCases/
│
├── Http/
│   ├── Requests/
│   │   ├── Auth/              # 認証関連
│   │   │   ├── SignInRequest.php
│   │   │   ├── SignUpRequest.php
│   │   │   └── VersionRequest.php
│   │   ├── Player/            # プレイヤー関連
│   │   │   ├── EquipRequest.php
│   │   │   └── InventoryRequest.php
│   │   ├── Gacha/             # ガチャ関連
│   │   │   └── DrawRequest.php
│   │   └── Mission/           # ミッション関連
│   │       ├── ListRequest.php
│   │       └── ClaimRequest.php
│   │
│   └── Responses/
│       ├── Auth/              # 認証関連
│       │   ├── SignInResponse.php
│       │   ├── SignUpResponse.php
│       │   └── VersionResponse.php  # ← MaintenanceDataなどを使用
│       ├── Player/            # プレイヤー関連
│       │   ├── MeResponse.php
│       │   ├── EquipResponse.php
│       │   └── InventoryResponse.php
│       ├── Gacha/             # ガチャ関連
│       │   └── DrawResponse.php
│       └── Mission/           # ミッション関連
│           ├── ListResponse.php
│           └── ClaimResponse.php
```

### 理由

- **可読性**: 関連するクラスがまとまっているため、探しやすい
- **スケーラビリティ**: 機能が増えても、ディレクトリ構造を維持できる
- **名前空間の明確化**: 機能ごとに名前空間が分かれるため、クラス名の衝突を避けられる

---

## 名前空間とクラスの配置

### 名前空間のルール

名前空間は、ディレクトリ構造に従います。

```php
// app/Http/Requests/Auth/VersionRequest.php
namespace App\Http\Requests\Auth;

class VersionRequest extends FormRequest
{
    // ...
}
```

```php
// app/Http/Responses/Auth/VersionResponse.php
namespace App\Http\Responses\Auth;

use App\Domain\Auth\Data\AssetUpdateData;
use App\Domain\Auth\Data\MaintenanceData;
use App\Domain\Auth\Data\MasterUpdateData;

class VersionResponse implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly ?int $latestDeployId = null,
        public readonly ?int $latestDeployKey = null,
        public readonly ?MasterUpdateData $master = null,
        public readonly ?AssetUpdateData $asset = null,
        public readonly ?MaintenanceData $maintenance = null,
    ) {}
    // ...
}
```

```php
// app/Domain/Auth/Data/MaintenanceData.php
namespace App\Domain\Auth\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class MaintenanceData implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $startAt,
        public readonly string $endAt,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
```

### 使用例

```php
// app/Http/Controllers/AuthController.php
namespace App\Http\Controllers;

use App\Http\Requests\Auth\VersionRequest;
use App\Http\Responses\Auth\VersionResponse;
use App\Domain\Auth\UseCases\VersionUseCase;

class AuthController extends Controller
{
    public function version(VersionRequest $request, VersionUseCase $useCase): JsonResponse
    {
        $response = $useCase->handle($request);
        return $response->toJsonResponse();
    }
}
```

---

## その他の規約

### 新しいAPIを追加する際の手順

新しいAPIエンドポイントを追加する際は、以下の手順に従ってください：

#### 1. ディレクトリの作成

機能ごとに適切なサブディレクトリを作成します。

```bash
# 例: Gacha機能の追加
mkdir -p app/Http/Requests/Gacha
mkdir -p app/Http/Responses/Gacha
```

#### 2. Requestクラスの作成

エンドポイントのアクション名に合わせたクラスを作成します。

```php
// app/Http/Requests/Gacha/DrawRequest.php
namespace App\Http\Requests\Gacha;

use Illuminate\Foundation\Http\FormRequest;

class DrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gacha_id' => 'required|string',
            'count' => 'required|integer|min:1|max:10',
        ];
    }
}
```

#### 3. Responseクラスの作成

```php
// app/Http/Responses/Gacha/DrawResponse.php
namespace App\Http\Responses\Gacha;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class DrawResponse implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly array $rewards,
        public readonly int $remainingCount,
    ) {
    }

    public function toArray(): array
    {
        return [
            'rewards' => $this->rewards,
            'remaining_count' => $this->remainingCount,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toJsonResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->toArray());
    }
}
```

#### 4. Controllerでの使用

```php
// app/Http/Controllers/GachaController.php
namespace App\Http\Controllers;

use App\Http\Requests\Gacha\DrawRequest;
use App\Domain\Gacha\UseCases\DrawUseCase;
use Illuminate\Http\JsonResponse;

class GachaController extends Controller
{
    public function draw(DrawRequest $request, DrawUseCase $useCase): JsonResponse
    {
        $response = $useCase->handle($request);
        return $response->toJsonResponse();
    }
}
```

#### 5. ルート定義

```php
// routes/api.php
use App\Http\Controllers\GachaController;

Route::middleware('auth.token')->group(function () {
    Route::post('/gacha/draw', [GachaController::class, 'draw']);
});
```

### Requestクラスを作成しない場合

以下の場合は、Requestクラスを作成せず、標準の`Illuminate\Http\Request`を使用しても構いません：

- **バリデーションが不要**な場合（例: `GET /player/me`）
- **リクエストパラメータがない**場合
- **認証トークンのみ必要**な場合

```php
// 標準Requestを使用する例
public function getPlayerMe(Request $request, GetPlayerMeUseCase $useCase): JsonResponse
{
    $response = $useCase->handle($request);
    return $response->toJsonResponse();
}
```

---

## クラスの責務

### Request
- バリデーションルールの定義
- リクエストデータの取得・変換
- リクエストの認可

### Response
- レスポンスデータの構造化
- JSON形式への変換
- HTTPステータスコードの設定

### Data（DTO）
- ドメインデータの型安全な表現
- 不変オブジェクトとして実装（readonly）
- Responseクラスや複数のServiceで共有されるデータ構造
- ビジネスロジックは含まない

### Repository
- データアクセスの抽象化
- キャッシュ管理
- 複雑なクエリの実装
- データ整合性の保証

### Model
- データベーステーブルとのマッピング
- リレーション定義
- クエリスコープ定義
- アクセサ/ミューテータ（値の取得・設定時の変換）
- ビジネスロジックは含まない

#### Mst Model の特別なルール
- `App\Models\Mst\` 配下のMstモデルは、マスターデータテーブルを扱う
- **基底クラス**: `_BaseMst` を継承（`$connection = 'mst'` が設定済み）
- **$fillable**: `deploy_key` を**必ず最初**に配置する（統一ルール）
- **deploy_key**: マスターデータのバージョン管理用カラム（例: `202601010`）

**Mst Model $fillable の例:**
```php
// ✅ 正しい実装
class MstUnit extends _BaseMst
{
    protected $fillable = [
        'deploy_key',  // 必ず最初
        'id',
        'type',
        'element',
        'rarity',
        // ...
    ];
}

// ❌ 誤った実装
class MstUnit extends _BaseMst
{
    protected $fillable = [
        'id',
        'deploy_key',  // 最初ではない
        'type',
        // ...
    ];
}
```

**理由:**
- **一貫性**: 全てのMstモデルで`deploy_key`の位置が統一され、可読性向上
- **バージョン管理の明確化**: マスターデータのバージョン管理カラムであることが視覚的に明確
- **新規モデル追加時の迷いを排除**: 規約が明確なため、実装時に判断不要

#### Log Model の特別なルール
- `App\Models\Log\` 配下のLogモデルは、insert onlyのログテーブルを扱う
- **基底クラス**: `_BaseLog` を継承（`$connection = 'log'`, `UPDATED_AT = null` が設定済み）
- **$casts**: ビジネスカラムと `system_at` のみ含める（`created_at`, `updated_at` は含めない）
- **$fillable**: ビジネスカラムと `system_at` のみ含める（`created_at` は含めない）
- **日時カラム**:
  - `system_at`: APIから明示的に設定（デバッグ時の日時変更に連動する）
  - `created_at`: MySQL CURRENT_TIMESTAMPで自動設定（デバッグ時の日時変更に連動しない）
  - `updated_at`: 存在しない（ログは更新されないため）

#### ローカライズテーブル (L10n) の重要なルール
- **ローカライズテーブル（`*_l10n`）にはModelクラスを作成しない**
- マスターデータのJSON配信時に、メインテーブルとL10nテーブルをJOINしてJSON化する
- 理由:
  - ローカライズデータは単独で扱うことがなく、常にメインテーブルと一緒に使用される
  - Modelを作成すると無駄なファイルが増え、保守性が低下する
  - Repository層でJOINクエリを実装すれば十分

**例:**
```php
// ❌ 作成不要
// App\Models\Mst\MstUnitL10n.php
// App\Models\Mst\MstItemL10n.php
// App\Models\Mst\MstEquipmentL10n.php

// ✅ Repositoryで直接クエリ
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

### Utility
- 汎用的なヘルパー機能の提供
- 静的メソッドで実装
- ステートレス（状態を持たない）
- プロジェクト全体で再利用可能な機能

### Constants（定数クラス）
- ビジネスドメインに関連する定数の定義
- `App\Domain\{Domain}\Constants\` 配下に配置
- Modelから独立させ、ドメイン知識をDomainレイヤーに集約
- クラス名は `*Const` サフィックスをつける
- バリデーションやヘルパーメソッドを提供可能

#### Constants配置ルール
- **NG**: Modelクラスに定数を直接定義
  ```php
  // ❌ 避けるべき実装
  class MstUnit extends _BaseMst
  {
      const TYPE_ATTACK = 'Attack';
      const ELEMENT_FIRE = 'Fire';
      const RARITY_UR = 'UR';
  }
  ```

- **OK**: Domainレイヤーに定数クラスを作成
  ```php
  // ✅ 推奨される実装
  namespace App\Domain\Unit\Constants;
  
  class UnitConst
  {
      const TYPE_ATTACK = 'Attack';
      const ELEMENT_FIRE = 'Fire';
      const RARITY_UR = 'UR';
      
      // ヘルパーメソッド
      public static function allTypes(): array { ... }
      public static function isValidType(string $type): bool { ... }
  }
  ```

#### Constants配置例
```
app/
├── Domain/
│   ├── Unit/
│   │   ├── Constants/
│   │   │   └── UnitConst.php            # ユニット関連の定数
│   │   ├── Services/
│   │   └── UseCases/
│   ├── Equipment/
│   │   ├── Constants/
│   │   │   └── EquipmentConst.php       # 装備関連の定数
│   │   ├── Services/
│   │   └── UseCases/
│   ├── Item/
│   │   ├── Constants/
│   │   │   └── ItemConst.php            # アイテム関連の定数
│   │   ├── Services/
│   │   └── UseCases/
│   ├── Billing/
│   │   ├── Constants/
│   │   │   └── BillingConst.php         # 決済プラットフォーム関連の定数
│   │   ├── Services/
│   │   └── UseCases/
│   ├── InAppPurchase/
│   │   ├── Constants/
│   │   │   └── InAppPurchaseConst.php   # アプリ内課金関連の定数
│   │   ├── Services/
│   │   └── UseCases/
│   └── Player/
│       ├── Constants/
│       │   └── PlayerConst.php          # プレイヤー関連の定数
│       ├── Services/
│       └── UseCases/
```

#### Constantsのメリット
- **関心の分離**: Modelはデータ構造のみに集中
- **再利用性**: 定数を複数のサービスやユースケースから参照可能
- **バリデーション機能**: `isValid*()` メソッドで値の検証が容易
- **配列取得機能**: `getAll*()` メソッドでUI生成が簡単
- **DDD準拠**: ドメイン知識をDomainレイヤーに集約

#### Constants使用例

**ItemConst（アイテム定数）:**
```php
use App\Domain\Item\Constants\ItemConst;

// タイプの検証
if (ItemConst::isValidType($itemType)) {
    // ...
}

// 全タイプを取得してUIに表示
$types = ItemConst::allTypes();

// 定数を使用
if ($item->type === ItemConst::TYPE_CONSUMABLE) {
    // 消費アイテム処理
}
```

**BillingConst（決済プラットフォーム定数）:**
```php
use App\Domain\Billing\Constants\BillingConst;

// プラットフォームの検証
if (BillingConst::isValidPlatform($platform)) {
    // ...
}

// App Store商品の判定
if ($product->billing_platform === BillingConst::PLATFORM_APP_STORE) {
    // App Store固有の処理
}

// 商品タイプの検証
if (BillingConst::isValidProductType($productType)) {
    // ...
}
```

**InAppPurchaseConst（アプリ内課金定数）:**
```php
use App\Domain\InAppPurchase\Constants\InAppPurchaseConst;

// 課金タイプの判定
if ($purchase->type === InAppPurchaseConst::TYPE_PASS) {
    // Pass商品の処理
    $effects = $purchase->effects;
}

// 購入制限リセットの判定
if ($purchase->purchase_limit_reset === InAppPurchaseConst::PURCHASE_LIMIT_RESET_DAILY) {
    // 日次リセット処理
}

// コンテンツタイプの検証
if (InAppPurchaseConst::isValidContentType($contentType)) {
    // コンテンツの配布処理
}

// 全効果タイプを取得
$effectTypes = InAppPurchaseConst::getAllEffectTypes();
```

---

## ファイル命名規則

### 基本ルール
- **ファイル名 = クラス名**
- PascalCase を使用
- 1ファイル1クラス

### 命名パターン

#### Request・Response
- エンドポイントのアクション名に合わせる
- 冗長な接頭辞・接尾辞を避ける

#### Data（DTO）
- 必ず `*Data` サフィックスをつける
- ✅ `AssetUpdateData`, `MaintenanceData`, `MasterUpdateData`
- ❌ `AssetUpdate`, `Maintenance`, `MasterUpdate`（曖昧）

#### Repository
- 必ず `*Repository` サフィックスをつける
- テーブル名に対応させる
- ✅ `SysPlayerRepository`, `MstItemRepository`, `TrxUnitRepository`

#### Model
- テーブル名をPascalCaseに変換
- データベースプレフィックスを含める
- ✅ `SysPlayer` (`sys_player`テーブル)
- ✅ `MstItem` (`mst_item`テーブル)
- ✅ `TrxPlayer` (`trx_player`テーブル)
- ✅ `LogEquipment` (`log_equipment`テーブル) - Logモデルは特別なルールあり

**Mst Model 特有の実装:**
- ✅ `_BaseMst` を継承する
- ✅ `$fillable` の**最初**に `deploy_key` を配置する（必須）
- ✅ 例: `protected $fillable = ['deploy_key', 'id', 'type', ...];`
- ❌ 例: `protected $fillable = ['id', 'deploy_key', ...];`（deploy_keyが最初ではない）

**Log Model 特有の実装:**
- ✅ `_BaseLog` を継承する
- ✅ `$casts` にビジネスカラムと `system_at` を含める
- ❌ `$casts` に `created_at`, `updated_at` を含めない
- ✅ `$fillable` にビジネスカラムと `system_at` を含める
- ❌ `$fillable` に `created_at`, `updated_at` を含めない

#### Utility
- 必ず `*Utility` サフィックスをつける
- 機能を表す明確な名前
- ✅ `StringUtility`, `DateUtility`, `RandomUtility`, `CryptoUtility`
- ✅ `ClockUtility`, `RedisUtility`（既存）
- ❌ `Helper`, `Utils`, `Common`（曖昧）

#### Constants
- 必ず `*Const` サフィックスをつける
- ドメイン名を含める
- ✅ `UnitConst`, `EquipmentConst`, `PlayerConst`
- ❌ `Unit`, `Equipment`, `Player`（曖昧）
- ❌ `UnitConstants`, `EquipmentConstants`（冗長）

---

### 命名禁止事項

以下のような冗長な命名は避けてください：

**Request/Response:**
- ❌ `VersionCheckRequest` → ✅ `VersionRequest`
- ❌ `GetVersionRequest` → ✅ `VersionRequest`
- ❌ `VersionCheckResponse` → ✅ `VersionResponse`
- ❌ `GetVersionResponse` → ✅ `VersionResponse`

**Data:**
- ❌ `AssetUpdate` → ✅ `AssetUpdateData`（サフィックス必須）
- ❌ `Maintenance` → ✅ `MaintenanceData`（サフィックス必須）

**Utility:**
- ❌ `Helper`, `Utils`, `Common` → ✅ `StringUtility`, `DateUtility`（具体的に）
- ❌ `StringUtil` → ✅ `StringUtility`（フルサフィックス）

**Constants:**
- ❌ `Unit`, `Equipment` → ✅ `UnitConst`, `EquipmentConst`（サフィックス必須）
- ❌ `UnitConstants` → ✅ `UnitConst`（冗長を避ける）

---

## まとめ

1. **Request・Responseクラス名は、エンドポイントのアクション名に合わせる**
2. **Dataクラスには `*Data` サフィックスをつけ、Domain層に配置する**
3. **Repositoryクラスには `*Repository` サフィックスをつけ、キャッシュ機能を実装する**
4. **Modelクラスはテーブル名をPascalCaseに変換し、プレフィックスを含める**
5. **Mst Modelは `$fillable` の最初に `deploy_key` を必ず配置する**
6. **Log Modelは `_BaseLog` を継承し、`$casts`/`$fillable` に `created_at`/`updated_at` を含めない**
7. **Utilityクラスには `*Utility` サフィックスをつけ、静的メソッドで実装する**
8. **Constantsクラスには `*Const` サフィックスをつけ、Domain層に配置する**
9. **Modelに定数を直接定義せず、Constantsクラスに分離する**
10. **機能ごとにサブディレクトリに分けて配置する**
11. **名前空間はディレクトリ構造に従う**
12. **冗長な接頭辞・接尾辞は避ける**

これらの規約に従うことで、コードの一貫性と保守性が向上します。


