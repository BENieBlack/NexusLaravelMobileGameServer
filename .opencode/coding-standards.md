# コーディング規約 / Coding Standards

このドキュメントでは、各レイヤーのクラス実装における具体的なルールを定義します。

## 目次

- [0. 日時操作のルール](#0-日時操作のルール)
- [1. Controllerの実装ルール](#1-controllerの実装ルール)
- [2. UseCaseの実装ルール](#2-usecaseの実装ルール)
- [3. Serviceの実装ルール](#3-serviceの実装ルール)
- [4. Requestの実装ルール](#4-requestの実装ルール)
- [5. Responseの実装ルール](#5-responseの実装ルール)
- [6. Modelの実装ルール](#6-modelの実装ルール)
- [7. Repositoryの実装ルール](#7-repositoryの実装ルール)
- [8. Utilityクラスの実装ルール](#8-utilityクラスの実装ルール)

---

## 0. 日時操作のルール

### 原則1: ClockUtilityを経由して時刻を取得

**すべての日時操作は`ClockUtility`を経由して行います。** `Carbon`や`CarbonImmutable`を直接使用しません。

```php
// ✅ Good: ClockUtilityを使用
use NexusUtilities\ClockUtility;

$now = ClockUtility::now(); // CarbonImmutableを返す
$tomorrow = $now->addDay();
$yesterday = $now->subDay();

// ❌ Bad: CarbonやCarbonImmutableを直接使用しない
use Carbon\Carbon;
use Carbon\CarbonImmutable;

$now = CarbonImmutable::now(); // NG
$now = Carbon::now(); // NG
```

### ClockUtilityを使用する理由

1. **テスト時の時刻制御が一元化される**: `ClockUtility::setNow()`でテスト時刻を固定できる
2. **パフォーマンス向上**: 大量のレコード処理時にCarbonオブジェクトの生成を避けられる
3. **コードの一貫性**: 時刻取得の方法が統一される
4. **予期しない変更を防ぐ**: CarbonImmutableを使用しているため、元の値が書き換わらない

### 原則2: 日時の比較方法を使い分ける

#### 2-1. NOW（現在時刻）との比較

現在時刻との比較には`ClockUtility`の比較メソッドを使用します。

**重要な原則**: このシステムでは日時を`00:00:00~23:59:59`の範囲で表現します。

比較メソッドの使い方：
- `ClockUtility::greaterThanOrEqual($startAt)` → `NOW >= $startAt`（開始済み）
- `ClockUtility::lessThanOrEqual($endAt)` → `NOW <= $endAt`（未終了）
- **`isAfter`と`isBefore`は曖昧なため使用禁止**

```php
// ✅ Good: greaterThanOrEqual/lessThanOrEqualを使用
use NexusUtilities\ClockUtility;

// 開始判定: まだ開始していない（NOW < start_at）
if (!ClockUtility::greaterThanOrEqual($gacha->start_at)) {
    throw new GameException('ガチャはまだ開始していません');
}

// 終了判定: すでに終了している（NOW > end_at）
if (!ClockUtility::lessThanOrEqual($gacha->end_at)) {
    throw new GameException('ガチャは終了しました');
}

// 期間内チェック: 開始済みかつ未終了
if (ClockUtility::greaterThanOrEqual($event->start_at) 
    && ClockUtility::lessThanOrEqual($event->end_at)) {
    // イベント期間内
}

// ❌ Bad: isAfterとisBeforeは使用禁止（曖昧）
if (ClockUtility::isAfter($gacha->start_at)) { // NG
    // ...
}

// ❌ Bad: parseしてから比較しない
$startAt = ClockUtility::parse($gacha->start_at);
if (self::now()->greaterThanOrEqualTo($startAt)) { // NG: parseは不要
    // ...
}
```

**判定ロジックの例**:
- `start_at = "2024-01-15 00:00:00"` → 2024年1月15日の0時から開始
- `end_at = "2024-01-20 23:59:59"` → 2024年1月20日の23時59分59秒まで有効
- `NOW = "2024-01-16 12:00:00"`の場合
  - `ClockUtility::greaterThanOrEqual($startAt)` → true（開始済み）
  - `ClockUtility::lessThanOrEqual($endAt)` → true（未終了）

#### 2-2. 2つの日時文字列（Y-m-d H:i:s）の比較

2つの日時文字列を比較する場合は、**文字列比較**を使用します。Carbonオブジェクトに変換しません。

```php
// ✅ Good: 文字列比較を使用（パフォーマンス最適化）
$lastLoginDate = substr($player->last_login_at, 0, 10); // "2024-01-15"
$todayString = ClockUtility::now()->toDateString(); // "2024-01-16"

if ($lastLoginDate < $todayString) {
    // 最終ログインが今日より前
    $this->grantLoginBonus($player);
}

// 日時文字列の比較（Y-m-d H:i:s形式）
if ($order->created_at < $campaign->end_at) {
    // キャンペーン期間内の注文
}

// ❌ Bad: Carbonオブジェクトに変換して比較しない
$lastLogin = ClockUtility::parse($player->last_login_at); // NG: 不要なparse
$today = ClockUtility::now();
if ($lastLogin->startOfDay()->lt($today->startOfDay())) { // NG: 重い処理
    // ...
}
```

**なぜ文字列比較を使うのか:**
- Y-m-d H:i:s形式の文字列は辞書順でソート可能
- Carbonオブジェクトの生成コストを避けられる
- 大量のレコード処理時にパフォーマンスが向上する

#### 2-3. 時間差分の計算とCarbonメソッドの使用

時間の差分を計算する場合や、年月週の情報が必要な場合は、`ClockUtility`の専用メソッドを使用します。

```php
// ✅ Good: ClockUtilityの専用メソッドを使用
$elapsedSeconds = ClockUtility::diffInSeconds($stamina->last_recovery_at);
$recoveredPoints = (int)floor($elapsedSeconds / self::RECOVERY_INTERVAL_SECONDS);

// ✅ Good: 年月週の判定もClockUtilityで
if (!ClockUtility::isToday($purchase->last_reset_at)) {
    // 今日リセットされていない
}

$now = ClockUtility::now();
if ($now->weekOfYear !== ClockUtility::weekOfYear($purchase->last_reset_at)) {
    // 週が変わった
}

if ($now->month !== ClockUtility::month($purchase->last_reset_at)) {
    // 月が変わった
}

// ❌ Bad: parse()を使わない（ClockUtilityの専用メソッドで十分）
$lastRecoveryAt = ClockUtility::parse($stamina->last_recovery_at); // NG
$elapsedSeconds = $now->diffInSeconds($lastRecoveryAt); // NG
```

**ClockUtilityの時間差分メソッド:**
- `diffInSeconds(string $dateTimeString): int` - 秒数差分
- `diffInMinutes(string $dateTimeString): int` - 分数差分
- `diffInHours(string $dateTimeString): int` - 時間数差分
- `diffInDays(string $dateTimeString): int` - 日数差分

**ClockUtilityの日時情報取得メソッド:**
- `isToday(string $dateTimeString): bool` - 今日かどうか
- `weekOfYear(string $dateTimeString): int` - 週番号（1-53）
- `month(string $dateTimeString): int` - 月（1-12）
- `year(string $dateTimeString): int` - 年

#### 2-4. parse()の使用（限定的）

`parse()`メソッドは以下の限定的なケースでのみ使用してください。

```php
// ✅ Good: 外部入力をCarbonImmutableに変換（Admin画面等）
$startAt = ClockUtility::parse($validated['start_at']);
$info = new MaintenanceInfo(
    startAt: $startAt,
    // ...
);

// ✅ Good: DTOの復元
public static function fromArray(array $data): self
{
    return new self(
        expireAt: isset($data['expire_at']) ? ClockUtility::parse($data['expire_at']) : null,
    );
}

// ❌ Bad: 通常のビジネスロジックでparse()を使わない
$lastLoginAt = ClockUtility::parse($player->last_login_at); // NG
if ($lastLoginAt->startOfDay()->lt($today)) { // NG
    // diffInSecondsやisAfter等のメソッドを使うべき
}
```

**parse()を使うべきケース:**
1. **外部入力の変換** - Admin画面等のバリデーション済み日時文字列をCarbonImmutableに変換
2. **DTOの復元** - 配列やJSONから日時オブジェクトを復元する場合

**parse()を使わないケース（ほとんどのケース）:**
- 時間差分の計算 → `ClockUtility::diffInSeconds()`等を使用
- 年月週の判定 → `ClockUtility::isToday()`, `weekOfYear()`等を使用
- NOW比較 → `ClockUtility::isAfter()`等を使用
- 2つの日時文字列比較 → 文字列比較を使用

### ClockUtilityの主要メソッド

#### 時刻取得

```php
ClockUtility::now(): CarbonImmutable
// 現在時刻を取得（テスト時は固定時刻を返す）

ClockUtility::nowToString(): string
// 現在時刻を文字列で取得（Y-m-d H:i:s形式）
```

#### NOW比較メソッド（最も頻繁に使用）

**注意**: `isAfter`と`isBefore`は曖昧なため使用禁止。代わりに`greaterThanOrEqual`と`lessThanOrEqual`を使用してください。

```php
ClockUtility::greaterThanOrEqual($dateTimeString): bool
// NOW >= 指定日時（開始済み）
// 用途: 開始済みチェック
// 例: if (ClockUtility::greaterThanOrEqual($gacha->start_at)) → ガチャ開始済み
// 例: if (!ClockUtility::greaterThanOrEqual($gacha->start_at)) → ガチャ未開始

ClockUtility::lessThanOrEqual($dateTimeString): bool
// NOW <= 指定日時（未終了）
// 用途: 未終了チェック
// 例: if (ClockUtility::lessThanOrEqual($gacha->end_at)) → ガチャ未終了
// 例: if (!ClockUtility::lessThanOrEqual($gacha->end_at)) → ガチャ終了済み
```

#### 時間差分メソッド

```php
ClockUtility::diffInSeconds(string $dateTimeString): int
// 現在時刻との秒数差分

ClockUtility::diffInMinutes(string $dateTimeString): int
// 現在時刻との分数差分

ClockUtility::diffInHours(string $dateTimeString): int
// 現在時刻との時間数差分

ClockUtility::diffInDays(string $dateTimeString): int
// 現在時刻との日数差分
```

#### 日時情報取得メソッド

```php
ClockUtility::isToday(string $dateTimeString): bool
// 指定日時が今日かどうか

ClockUtility::weekOfYear(string $dateTimeString): int
// 指定日時の週番号（1-53）

ClockUtility::month(string $dateTimeString): int
// 指定日時の月（1-12）

ClockUtility::year(string $dateTimeString): int
// 指定日時の年
```

#### parse()メソッド（限定的な使用）

```php
ClockUtility::parse(string $time, $tz = null): CarbonImmutable
// 日時文字列をCarbonImmutableに変換
// 使用ケース: 外部入力の変換、DTOの復元のみ
// 通常のビジネスロジックでは上記の専用メソッドを使用
```

#### テスト用メソッド

```php
ClockUtility::setNow(CarbonImmutable $datetime): void
// テスト時の時刻を設定

ClockUtility::reset(): void
// テスト時刻をリセット（現在時刻に戻す）
```

### Eloquentモデルでの日時カラムの扱い

#### ❌ Bad: datetimeキャストは使わない

```php
// ❌ Bad: datetimeキャストはCarbonオブジェクトを生成してしまう
protected $casts = [
    'last_login_at' => 'datetime', // NG: レコード取得時に毎回Carbonが生成される
];
```

#### ✅ Good: 文字列として扱い、getter/setterで型を定義

```php
// ✅ Good: datetimeキャストを使わず、getter/setterで文字列として扱う
protected $casts = [
    'level' => 'integer',
    // last_login_atはキャストしない（文字列のまま）
];

/**
 * 最終ログイン日時を取得
 * @return string|null Y-m-d H:i:s形式
 */
public function getLastLoginAt(): ?string
{
    return $this->getAttribute('last_login_at');
}

/**
 * 最終ログイン日時を設定
 * @param string $lastLoginAt Y-m-d H:i:s形式
 */
public function setLastLoginAt(string $lastLoginAt): void
{
    $this->setAttribute('last_login_at', $lastLoginAt);
}
```

**なぜgetter/setterで文字列にするのか:**
- 大量のレコード取得時にCarbonオブジェクトの生成コストを回避
- 文字列比較でパフォーマンスを向上
- 必要な時だけ`ClockUtility::parse()`でCarbonに変換

### 使用例

#### ログインボーナスの判定

```php
public function checkAndGrantLoginBonus(int $sysPlayerId, ?string $lastLoginAt): array
{
    $currentTime = ClockUtility::now();
    $todayString = $currentTime->startOfDay()->toDateString(); // "2024-01-16"
    
    // 文字列比較: lastLoginAtから日付部分を取得して比較
    $lastLoginDate = $lastLoginAt !== null ? substr($lastLoginAt, 0, 10) : null;
    if ($lastLoginDate === null || $lastLoginDate < $todayString) {
        return $this->grantLoginBonus($sysPlayerId, $currentTime->startOfDay());
    }
    
    return [];
}
```

#### ガチャ期間のチェック

```php
public function validateGachaPeriod(MstGacha $gacha): void
{
    // NOW比較: ClockUtilityの比較メソッドを使用
    if ($gacha->start_at && ClockUtility::isAfter($gacha->start_at)) {
        throw new GameException('ガチャはまだ開始していません');
    }
    
    if ($gacha->end_at && ClockUtility::isBefore($gacha->end_at)) {
        throw new GameException('ガチャは終了しました');
    }
}
```

#### スタミナ回復の計算

```php
private function recoverStamina(TrxStamina $stamina, int $maxStamina): void
{
    // ClockUtilityの専用メソッドで時間差分を計算
    $elapsedSeconds = ClockUtility::diffInSeconds($stamina->last_recovery_at);
    $recoveredPoints = (int)floor($elapsedSeconds / self::RECOVERY_INTERVAL_SECONDS);
    
    if ($recoveredPoints > 0) {
        $newStamina = min($stamina->current_stamina + $recoveredPoints, $maxStamina);
        $stamina->setCurrentStamina($newStamina);
        $stamina->setLastRecoveryAt(ClockUtility::nowToString());
    }
}
```

#### 課金回数のリセットチェック

```php
private function shouldResetPurchaseCount(string $resetType, ?string $lastResetAt): bool
{
    if ($resetType === 'None' || $lastResetAt === null) {
        return false;
    }

    $now = ClockUtility::now();

    return match ($resetType) {
        'Daily' => !ClockUtility::isToday($lastResetAt),
        'Weekly' => $now->weekOfYear !== ClockUtility::weekOfYear($lastResetAt) 
                    || $now->year !== ClockUtility::year($lastResetAt),
        'Monthly' => $now->month !== ClockUtility::month($lastResetAt) 
                     || $now->year !== ClockUtility::year($lastResetAt),
        default => false,
    };
}
```

### ルール

#### ✅ 必ず守ること

- **すべての時刻取得は`ClockUtility::now()`を使用**（`Carbon::now()`や`CarbonImmutable::now()`は使わない）
- **NOW比較は`ClockUtility::greaterThanOrEqual()`と`lessThanOrEqual()`を使用**
  - 開始判定: `greaterThanOrEqual`（まだ開始していない）
  - 終了判定: `lessThanOrEqual`（すでに終了している）
  - **`isAfter`と`isBefore`は曖昧なため使用禁止**
- **時間差分は`ClockUtility::diffInSeconds()`等の専用メソッドを使用**
- **年月週情報は`ClockUtility::isToday()`, `weekOfYear()`等の専用メソッドを使用**
- **2つの日時文字列（Y-m-d H:i:s）の比較は文字列比較を使用**（`<`, `>`, `===`）
- **`parse()`は外部入力の変換とDTOの復元のみ使用**（通常のビジネスロジックでは使わない）
- **Eloquentモデルの日時カラムはdatetimeキャストを使わず、getter/setterで文字列型として扱う**
- **型定義としての`CarbonImmutable`は残してOK**（メソッドの引数や返り値の型）

#### ❌ やってはいけないこと

- `Carbon::now()`や`CarbonImmutable::now()`を直接呼び出す
- `Carbon::parse()`や`CarbonImmutable::parse()`を直接呼び出す
- **`isAfter()`や`isBefore()`を使う**（曖昧なため禁止、代わりに`greaterThanOrEqual`/`lessThanOrEqual`を使用）
- NOW比較や時間差分のために`parse()`を使う（専用メソッドで十分）
- 2つの日時文字列の比較でCarbonオブジェクトに変換する
- 日付の取得だけのために`parse()`を使う（`substr()`で十分）
- Eloquentモデルで`datetime`キャストを使う（大量レコード処理時のパフォーマンス劣化）
- 通常のビジネスロジックで`parse()`を使ってCarbonオブジェクトを生成する

---

## 1. Controllerの実装ルール

### 原則: Controllerは薄く保つ

```php
// ✅ Good: Controllerは薄く、UseCaseに処理を委譲
class AuthController extends Controller
{
    public function version(
        VersionCheckRequest $request,
        VersionCheckUseCase $useCase
    ): JsonResponse {
        $response = $useCase->handle($request);
        return $response->toJsonResponse();
    }
}

// ❌ Bad: Controllerにビジネスロジックを書かない
class AuthController extends Controller
{
    public function version(Request $request): JsonResponse
    {
        // バリデーション処理
        // データ取得処理
        // ビジネスロジック処理
        // レスポンス生成処理
        // ← 全部Controllerに書くのはNG
    }
}
```

### ルール

- Controllerのメソッドは**10行以内**を目安にする
- **Request → UseCase → Response** の流れのみ
- ビジネスロジックは書かない
- データアクセスは行わない
- 認証・認可はMiddlewareで行う

### Controllerに実装すべきもの

**✅ 実装すべき:**
- FormRequestの受け取り
- UseCaseの呼び出し
- Responseの返却

**❌ 実装してはいけない:**
- ビジネスロジック → UseCaseまたはServiceへ
- バリデーション → FormRequestへ
- データベースアクセス → ServiceまたはRepositoryへ

---

## 2. UseCaseの実装ルール

### 原則: ビジネスフローの制御に集中

```php
// ✅ Good: UseCaseはビジネスフローを制御
class VersionCheckUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly VersionCheckService $versionCheckService
    ) {}

    public function handle(VersionCheckRequest $request): VersionCheckResponse
    {
        $deployVersion = $request->getDeployVersion();
        return $this->versionCheckService->checkVersion($deployVersion);
    }
}
```

### トランザクション制御

```php
// ✅ Good: UseCaseでトランザクション制御
class ItemPurchaseUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly ItemService $itemService,
    ) {}

    public function handle(ItemPurchaseRequest $request): ItemPurchaseResponse
    {
        return DB::connection('trx1')->transaction(function () use ($request) {
            // 通貨を消費
            $this->walletService->consume(
                $request->getPlayerId(),
                WalletConst::CURRENCY_TYPE_GOLD,
                $request->getPrice()
            );

            // アイテムを付与
            $item = $this->itemService->grant(
                $request->getPlayerId(),
                $request->getItemId(),
                $request->getQuantity()
            );

            return new ItemPurchaseResponse($item);
        });
    }
}
```

### ルール

- **1つのUseCaseは1つのユースケース**を表現
- Serviceを組み合わせて処理フローを制御
- データベースに直接アクセスしない（Serviceを経由）
- **トランザクション制御はUseCaseで行う**
- 複数のServiceを組み合わせて使用可能

### UseCaseに実装すべきもの

**✅ 実装すべき:**
- ビジネスフローの制御
- Serviceの組み合わせ
- トランザクション制御
- エラーハンドリング

**❌ 実装してはいけない:**
- 具体的なビジネスロジック → Serviceへ
- データベースアクセス → ServiceまたはRepositoryへ
- バリデーション → FormRequestへ

---

## 3. Serviceの実装ルール

### 原則: ドメインロジックの実装

```php
// ✅ Good: Serviceにビジネスロジックを実装
class VersionCheckService
{
    public function checkVersion(?int $currentDeployId): VersionCheckResponse
    {
        $latestDeploy = SysDeploy::getLatestDownloadable();
        
        if ($latestDeploy === null) {
            return VersionCheckResponse::upToDate();
        }
        
        if ($currentDeployId === $latestDeploy->id) {
            return VersionCheckResponse::upToDate();
        }
        
        // ビジネスロジック実装...
        return new VersionCheckResponse(
            needsUpdate: true,
            latestDeployId: $latestDeploy->id,
            // ...
        );
    }
}
```

### Strategy Pattern（Handler）の使用

```php
// ✅ Good: Strategy Patternでビジネスロジックを分岐
class DeliveryService
{
    public function __construct(
        /** @var DeliveryHandlerInterface[] */
        private readonly array $handlers
    ) {}

    public function deliver(int $playerId, array $contents): array
    {
        $results = [];
        
        foreach ($contents as $content) {
            $handler = $this->findHandler($content);
            $results[] = $handler->deliver($playerId, $content);
        }
        
        return $results;
    }

    private function findHandler(DeliveryContent $content): DeliveryHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($content)) {
                return $handler;
            }
        }
        
        throw new UnsupportedResourceTypeException($content->resourceType);
    }
}
```

### ルール

- **再利用可能なビジネスロジック**を実装
- Eloquentモデルを使ってデータアクセス
- **複雑なクエリはRepositoryに切り出す**
- HTTPリクエスト/レスポンスに依存しない
- Strategy Patternを活用（Handler）

### Serviceに実装すべきもの

**✅ 実装すべき:**
- ビジネスロジックの実装
- Eloquentモデルの使用
- Repositoryの使用（複雑なクエリ）
- DTOの生成

**❌ 実装してはいけない:**
- HTTPリクエスト/レスポンスへの依存
- セッション管理
- 認証・認可ロジック（認証状態の確認は可）

---

## 4. Requestの実装ルール

### 原則: バリデーションと型安全性

```php
// ✅ Good: FormRequestでバリデーション
class VersionCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // または認証ロジック
    }

    public function rules(): array
    {
        return [
            // バリデーションルール
        ];
    }

    // 型安全なアクセサを提供
    public function getDeployVersion(): ?int
    {
        $version = $this->header('DeployVersion');
        return $version !== null ? (int) $version : null;
    }
}
```

### 複雑なバリデーション例

```php
class ItemPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'string', 'max:191'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'アイテムIDは必須です',
            'quantity.min' => '数量は1以上で指定してください',
        ];
    }

    // 型安全なアクセサ
    public function getItemId(): string
    {
        return $this->input('item_id');
    }

    public function getQuantity(): int
    {
        return (int) $this->input('quantity');
    }

    public function getPlayerId(): int
    {
        // 認証済みユーザーからプレイヤーIDを取得
        return $this->user()->sys_player_id;
    }
}
```

### ルール

- **すべてのリクエストパラメータをバリデーション**
- **型安全なアクセサメソッドを提供**（`getXxx()`）
- HTTPヘッダーのアクセスもRequestクラスで管理
- カスタムバリデーションルールは専用クラスに切り出す

### Requestに実装すべきもの

**✅ 実装すべき:**
- バリデーションルール（`rules()`）
- 型安全なアクセサメソッド
- カスタムエラーメッセージ（`messages()`）
- 認可ロジック（`authorize()`）

**❌ 実装してはいけない:**
- ビジネスロジック
- データベースアクセス（バリデーション用のクエリは例外）

---

## 5. Responseの実装ルール

### 原則: 明示的な型定義とDTO

```php
// ✅ Good: レスポンス構造を明示的に定義
class VersionCheckResponse extends _BaseResponse
{
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly ?int $latestDeployId = null,
        public readonly ?AssetUpdate $asset = null,
        public readonly ?MasterUpdate $master = null,
    ) {}

    public function toJsonResponse(): JsonResponse
    {
        return response()->json($this->toArray());
    }
    
    public function toArray(): array
    {
        $array = [
            'needs_update' => $this->needsUpdate,
        ];
        
        if ($this->latestDeployId !== null) {
            $array['latest_deploy_id'] = $this->latestDeployId;
        }
        
        if ($this->asset !== null) {
            $array['asset'] = $this->asset->toArray();
        }
        
        if ($this->master !== null) {
            $array['master'] = $this->master->toArray();
        }
        
        return $array;
    }
}
```

### ファクトリーメソッドの活用

```php
class VersionCheckResponse extends _BaseResponse
{
    // ファクトリーメソッドで生成を簡潔に
    public static function upToDate(): self
    {
        return new self(needsUpdate: false);
    }

    public static function needsUpdate(
        int $latestDeployId,
        ?AssetUpdate $asset = null,
        ?MasterUpdate $master = null
    ): self {
        return new self(
            needsUpdate: true,
            latestDeployId: $latestDeployId,
            asset: $asset,
            master: $master
        );
    }
}
```

### ルール

- レスポンス構造を型安全に定義
- **1ファイル = 1クラス**（PSR-4準拠）
- `toArray()` と `toJsonResponse()` メソッドを実装
- ファクトリーメソッドを活用
- null値は省略可能（`toArray()`で制御）

### Responseに実装すべきもの

**✅ 実装すべき:**
- コンストラクタ（readonly プロパティ）
- `toArray()` メソッド
- `toJsonResponse()` メソッド
- ファクトリーメソッド（必要に応じて）

**❌ 実装してはいけない:**
- ビジネスロジック
- データベースアクセス

---

## 6. Modelの実装ルール

### 原則: データアクセスとビジネスロジックの分離

### 基本構造

```php
// ✅ Good: スコープとリレーションの定義
class SysDeploy extends Model
{
    // データベース接続の明示的指定
    protected $connection = 'sys';
    protected $table = 'sys_deploy';

    // キャスト定義（型安全性の向上）
    protected $casts = [
        'is_active' => 'boolean',
        'start_at' => 'immutable_datetime',  // ← CarbonImmutable
        'end_at' => 'immutable_datetime',
    ];

    // Mass Assignmentの設定
    protected $fillable = [
        'deploy_key',
        'sys_deploy_master_id',
        'sys_deploy_asset_id',
        'is_active',
        'start_at',
    ];

    // リレーション定義
    public function deployMaster(): BelongsTo
    {
        return $this->belongsTo(SysDeployMaster::class, 'sys_deploy_master_id');
    }

    public function deployAsset(): BelongsTo
    {
        return $this->belongsTo(SysDeployAsset::class, 'sys_deploy_asset_id');
    }

    // クエリスコープ
    public function scopeDownloadable(Builder $query): void
    {
        $query->where('is_active', true)
              ->where('start_at', '<=', ClockUtility::now());
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
```

### Modelに実装すべきもの

**✅ 実装すべき:**
- データベース接続の指定（`$connection`）
- テーブル名の指定（`$table`）
- キャスト定義（`$casts`、`immutable_datetime`を使用）
- Mass Assignmentの設定（`$fillable` または `$guarded`）
- リレーションメソッド
- クエリスコープ
- アクセサ/ミューテータ（必要な場合のみ）

**❌ 実装してはいけない:**
- ビジネスロジック → Serviceへ
- 複雑なクエリロジック → Repositoryへ
- 外部API呼び出し → Serviceへ
- バリデーションロジック → FormRequestへ

### Log Modelの特別なルール

Logモデル（`App\Models\Log\`）は、insert onlyのログテーブルを扱うため、特別なルールがあります。

**基底クラス: `_BaseLog`**

```php
abstract class _BaseLog extends Model implements _BaseLogInterface
{
    /**
     * ログテーブルは log データベース接続を使用
     */
    protected $connection = 'log';

    /**
     * updated_atを使用しない（ログテーブルはinsert onlyのため）
     */
    const UPDATED_AT = null;
}
```

**Logモデルの実装例**

```php
// ✅ Good: Logモデルの正しい実装
class LogEquipment extends _BaseLog
{
    protected $table = 'log_equipment';

    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'trx_equipment_id' => 'integer',
        'mst_equipment_id' => 'string',
        'before_grade' => 'integer',
        'after_grade' => 'integer',
        'system_at' => 'immutable_datetime',
        // ❌ created_at は含めない（Laravelが自動的にCarbonにキャスト）
        // ❌ updated_at は含めない（テーブルに存在しない）
    ];

    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'trx_equipment_id',
        'mst_equipment_id',
        'before_grade',
        'after_grade',
        'system_at',
        // ❌ created_at は含めない（MySQL CURRENT_TIMESTAMPで自動設定）
    ];
}
```

**Logテーブルのカラム設計**

| カラム名 | 説明 | 設定方法 | デバッグ時の日時変更 |
|---------|------|----------|---------------------|
| `system_at` | システム日時 | APIから明示的に設定 | ✅ 連動する |
| `created_at` | 作成日時 | MySQL CURRENT_TIMESTAMP | ❌ 連動しない |

**Logモデルの重要なルール:**

✅ **実装すべき:**
- `_BaseLog`を継承する
- `$table`を明示的に指定
- `$casts`にビジネスカラムとsystem_atを含める
- `$fillable`にビジネスカラムとsystem_atを含める

❌ **実装してはいけない:**
- `$connection`の指定（`_BaseLog`で設定済み）
- `UPDATED_AT`の定義（`_BaseLog`で設定済み）
- `$casts`に`created_at`を含める（Laravelが自動キャスト）
- `$casts`に`updated_at`を含める（テーブルに存在しない）
- `$fillable`に`created_at`を含める（自動設定されるため）
- `$fillable`に`updated_at`を含める（テーブルに存在しない）

**注意事項:**
- ログテーブルは**insert only**（更新・削除は行わない）
- `unique_request_id`は`log_access`テーブルと結合するための一意ID
- ログデータは監査・分析用途のため、不変性を保つ

---

## 7. Repositoryの実装ルール

### 原則: データアクセスの抽象化とキャッシュ管理

```php
// ✅ Good: Repositoryでデータアクセスを抽象化
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

    public function selectByUuid(string $uuid): ?SysPlayer
    {
        return $this->cacheRemember(
            "uuid:{$uuid}",
            fn() => $this->newQuery()->where('uuid', $uuid)->first()
        );
    }
    
    public function selectListByShardId(int $shardId): Collection
    {
        return $this->cacheRemember(
            "shard_id:{$shardId}",
            fn() => $this->newQuery()->where('shard_id', $shardId)->get()
        );
    }
}
```

### メソッド命名規則

| 用途 | メソッド名 | 戻り値 | 例 |
|-----|-----------|-------|-----|
| ID検索 | `selectById(int $id)` | 単一モデル | `selectById(123)` |
| 単一レコード検索 | `selectByXxx()` | 単一モデル | `selectByMyId('player_001')` |
| 複数レコード検索 | `selectListByXxx()` | コレクション | `selectListByStatus('active')` |
| データ挿入 | `insert()` | モデル | `insert($data)` |
| データ更新 | `update()` | モデル | `update($model)` |
| データ削除 | `delete()` | bool | `delete($model)` |

### ルール

- 各データベース（Sys/Mst/Trx）ごとに基底クラスを継承
- **キャッシュ機能を積極的に活用**（重複クエリの削減）
- メソッド命名規則に従う
- キャッシュキーは一意性を保つ（例: `"sys:player:my_id:{$myId}"`）
- テスト環境では自動的にArrayキャッシュを使用

---

## 8. Utilityクラスの実装ルール

### 原則: 汎用的なヘルパー機能の提供

### 配置とディレクトリ構造

```
api/app/Utilities/
├── StringUtility.php       # 文字列操作ユーティリティ
├── DateUtility.php         # 日付・時刻操作ユーティリティ
├── CryptoUtility.php       # 暗号化・ハッシュ化ユーティリティ
├── RandomUtility.php       # ランダム値生成ユーティリティ
├── ValidationUtility.php   # カスタムバリデーションユーティリティ
├── ArrayUtility.php        # 配列操作ユーティリティ
├── ClockUtility.php        # 時刻管理ユーティリティ（既存）
└── ApiSession.php          # APIセッション管理（既存）
```

### 実装例

```php
// ✅ Good: 静的メソッドで汎用機能を提供
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

```php
// ✅ Good: ランダム値生成ユーティリティ
namespace App\Utilities;

class RandomUtility
{
    /**
     * 重み付きランダム抽選
     * 
     * @param array<string, int> $weights ['item_id' => weight, ...]
     * @return string 抽選結果のキー
     */
    public static function weightedRandom(array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = random_int(1, $totalWeight);
        
        $currentWeight = 0;
        foreach ($weights as $key => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $key;
            }
        }
        
        return array_key_first($weights);
    }

    public static function randomInt(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    public static function randomElement(array $array): mixed
    {
        if (empty($array)) {
            return null;
        }
        
        return $array[array_rand($array)];
    }
}
```

### Utilityクラスの特徴

**✅ 実装すべき:**
- 静的メソッド（`static`）で実装
- ステートレス（インスタンス変数を持たない）
- 汎用的で再利用可能な機能
- 単一責任の原則（1つのUtilityクラスは1つの領域に特化）
- 十分なテストカバレッジ

**❌ 実装してはいけない:**
- ビジネスロジック → Serviceへ
- データベースアクセス → Repository/Modelへ
- 外部API呼び出し → 専用のServiceへ
- 状態を持つ処理 → 通常のクラスへ

### Laravelヘルパー関数との使い分け

**Laravel標準ヘルパーを優先:**
- 配列操作: `Arr::*`
- 文字列操作: `Str::*`
- コレクション操作: `Collection`

**独自Utilityを作成すべき:**
- ゲーム固有のロジック（ガチャの重み付き抽選など）
- プロジェクト固有の変換処理
- 複雑な計算ロジック

### ルール

- `App\Utilities` 名前空間に配置
- クラス名には `*Utility` サフィックスをつける
- すべて静的メソッドで実装
- ステートレス（状態を持たない）
- 汎用的で再利用可能な機能のみ
- ビジネスロジックは含まない
- 十分なユニットテストを作成

---

## まとめ

### レイヤー別のチェックリスト

**Controller:**
- [ ] 10行以内に収まっているか
- [ ] ビジネスロジックを含んでいないか
- [ ] UseCaseに処理を委譲しているか

**UseCase:**
- [ ] 1つのユースケースを表現しているか
- [ ] トランザクション管理を適切に行っているか
- [ ] Serviceを組み合わせているか

**Service:**
- [ ] ビジネスロジックを実装しているか
- [ ] HTTPリクエスト/レスポンスに依存していないか
- [ ] 再利用可能な設計になっているか

**Request:**
- [ ] バリデーションルールを定義しているか
- [ ] 型安全なアクセサを提供しているか

**Response:**
- [ ] レスポンス構造を型安全に定義しているか
- [ ] `toArray()`と`toJsonResponse()`を実装しているか

**Model:**
- [ ] データアクセスのみを担当しているか
- [ ] ビジネスロジックを含んでいないか
- [ ] `immutable_datetime`キャストを使用しているか

**Repository:**
- [ ] データアクセスを抽象化しているか
- [ ] キャッシュを適切に使用しているか
- [ ] メソッド命名規則に従っているか

**Utility:**
- [ ] 静的メソッドで実装しているか
- [ ] ステートレスになっているか
- [ ] 汎用的な機能のみを提供しているか

## 関連ドキュメント

- [アーキテクチャ設計](./architecture.md) - レイヤー構造の詳細
- [命名規約](./naming-conventions.md) - クラスとディレクトリの命名規則
- [API設計](./api.md) - APIエンドポイントの設計
- [データベース設計](./database.md) - データベース層の設計

---

## 9. DTOとメタデータの活用

### 原則: DTOで型安全なデータ受け渡し

DTOは複数のサービス間でデータを受け渡す際に、型安全性と明示性を確保するために使用します。

### DeliveryContentの実装例

```php
// ✅ Good: DTOを使った型安全なデータ受け渡し
class DeliveryContent
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly int $amount,
        public readonly array $metadata = [],
        public readonly ?CarbonImmutable $expireAt = null,
    ) {}

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getExpireAt(): ?CarbonImmutable
    {
        return $this->expireAt;
    }
}
```

### メタデータ（metadata）の活用

メタデータは、オプショナルな情報を柔軟に追加するための配列です。

#### ✅ 推奨される使用例

```php
// 有償フラグをメタデータとして設定
$deliveryContent = new DeliveryContent(
    type: DeliveryConst::CONTENT_TYPE_DIAMOND,
    id: '1',
    amount: 100,
    metadata: ['is_paid' => true],  // 有償ダイアモンドフラグ
);

// Handlerでメタデータを参照
class DiamondDeliveryHandler implements DeliveryHandlerInterface
{
    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        $isPaid = $content->getMetadata()['is_paid'] ?? false;
        
        $freeAmount = $isPaid ? 0 : $content->getAmount();
        $paidAmount = $isPaid ? $content->getAmount() : 0;
        
        $this->diamondService->addDiamond(
            $sysPlayerId,
            $freeAmount,
            $paidAmount
        );
    }
}
```

#### メタデータに含めるべき情報

**✅ 含めるべき:**
- 有償/無償フラグ（`is_paid`）
- 有効期限（`expire_at`）※既にコンストラクタパラメータがある場合は不要
- プラットフォーム固有情報（`platform`、`billing_platform`）
- その他のオプショナルな属性

**❌ 含めるべきでない:**
- 必須パラメータ（typeやid、amountなど）
- ビジネスロジックの計算結果（Handlerで計算すべき）

### WalletとDiamondのHandler実装パターン

#### WalletDeliveryHandler

```php
class WalletDeliveryHandler implements DeliveryHandlerInterface
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        // メタデータから有償フラグを取得
        $isPaid = $content->getMetadata()['is_paid'] ?? false;
        
        // 有償/無償に分ける
        $freeAmount = $isPaid ? 0 : $content->getAmount();
        $paidAmount = $isPaid ? $content->getAmount() : 0;
        
        // WalletServiceを呼び出し
        $this->walletService->addCurrency(
            $sysPlayerId,
            $content->getId(),      // mst_item_id
            $freeAmount,
            $paidAmount,
            $content->getExpireAt() // 有効期限
        );
    }

    public function supports(string $type): bool
    {
        return $type === DeliveryConst::CONTENT_TYPE_WALLET;
    }
}
```

#### DiamondDeliveryHandler

```php
class DiamondDeliveryHandler implements DeliveryHandlerInterface
{
    public function __construct(
        private readonly DiamondService $diamondService,
    ) {}

    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        // メタデータから必要な情報を取得
        $isPaid = $content->getMetadata()['is_paid'] ?? false;
        $platform = $content->getMetadata()['platform'] ?? 'Apple';
        $billingPlatform = $content->getMetadata()['billing_platform'] ?? null;
        
        $freeAmount = $isPaid ? 0 : $content->getAmount();
        $paidAmount = $isPaid ? $content->getAmount() : 0;
        
        $this->diamondService->addDiamond(
            $sysPlayerId,
            $platform,
            $freeAmount,
            $paidAmount,
            $billingPlatform,
            $content->getExpireAt()
        );
    }

    public function supports(string $type): bool
    {
        return $type === DeliveryConst::CONTENT_TYPE_DIAMOND;
    }
}
```

### ルール

**DTO設計:**
- [ ] readonlyプロパティで不変性を保証
- [ ] コンストラクタで必須パラメータを受け取る
- [ ] metadataは配列型で柔軟性を確保
- [ ] Getterメソッドを提供

**メタデータ活用:**
- [ ] オプショナルな情報のみをメタデータに格納
- [ ] is_paidフラグは標準的なメタデータとして使用
- [ ] デフォルト値を明示的に設定（`?? false`など）
- [ ] Handlerでメタデータを参照してビジネスロジックを分岐

**Handler実装:**
- [ ] メタデータから情報を取り出し、Serviceに適切な形式で渡す
- [ ] 有償/無償の分岐はHandlerで行う
- [ ] サービスメソッドのシグネチャに合わせてパラメータを変換
