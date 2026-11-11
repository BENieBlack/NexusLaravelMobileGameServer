# コーディング規約

Controller / Request / UseCase / Service / Repository / Model / Response / DTO / 例外 / テストを新しく書く・直すときに、そのクラス種別の節を読む。
レイヤーの全体像とモジュール境界は architecture.md、テーブル・カラムは database.md、APIのキーとエラー形式は api.md、名前の付け方は naming-conventions.md。

## Controller

- `api/app/Http/Controllers/{Context}Controller.php`、`_BaseController` を継承する。
- アクションは `$this->execute(fn () => $useCase->handle(...))` を返すだけにする。`execute()` は UseCase が返した Response（`App\Domain\_BaseResponse`）を JSON（HTTP 200）にする。それ以外を返すと `LogicException`、例外は `handleException()` がエラーレスポンスにする。Controller で try/catch しない。
- UseCase の入口は `handle()`。Request オブジェクトは渡さず、Request の getter で取り出した値を渡す。
- Controller では Response を作らない（`XxxResponse::from()` や `new XxxResponse` を書かない）。
- プレイヤーIDは `ApiSession::getSysPlayerId()`（UnitController）か `$this->requireAuthenticatedPlayerId($request->resolveAuthenticatedPlayerId())`（FriendController, ChatController）で取る。現状は両方ある。

例: `api/app/Http/Controllers/UnitController.php`

```php
public function levelUp(LevelUpRequest $request): JsonResponse
{
    return $this->execute(fn () => $this->unitLevelUpUseCase->handle(
        sysPlayerId: $this->apiSession->getSysPlayerId(),
        trxUnitId: $request->getTrxUnitId(),
        mstItemId: $request->getMstItemId(),
        useCount: $request->getUseCount(),
    ));
}
```

## Request

- `api/app/Http/Requests/{Context}/{Action}Request.php`、`_BaseRequest`（FormRequest）を継承する。`rules()` は abstract で必須、`messages()` / `attributes()` は任意、`authorize()` は基底が true を返す。
- 入力は型付きの getter で取り出す。整数は `(int) $this->input('trx_unit_id')`、文字列は `(string) $this->input(...)`、bool は `$this->boolean(...)`。`$this->integer()` は使っていない。
- 列挙値の検証は `Rule::in(...)`（`Http/Requests/Mailbox/ListRequest.php`）。独自の Rule クラス（`App\Rules`）は無い。
- `*_id` のキーは ResponseKeyNamingTest の対象（テーブル接頭辞を付ける）。

例: `api/app/Http/Requests/Unit/LevelUpRequest.php`

## UseCase

- `api/app/Domain/{Context}/UseCases/{Action}UseCase.php`、`App\Domain\_BaseUseCase` を継承する。
- 入口は `handle()`。引数はスカラーかDTOで、シグネチャは UseCase ごとに決める。
- 事前検証は同じ引数の `validation()` に書き、`handle()` の先頭で呼ぶ（存在確認・所有確認・所持数など）。
- `validation()` で取得したモデル（プレイヤー、マスター、所有物）は、戻り値で返して `handle()` で使う。`handle()` で同じ `selectById()` をもう一度呼ばない（`?? throw` で null を潰す再取得が要らなくなる）。
- 書き込みがある処理は `executeWithTransaction(fn () => ...)` の中で行う。読み取りだけなら不要。INSERT直後に採番IDを参照するときだけ `flushQueue()` を呼ぶ。
- 戻り値は自分専用の Response（`Domain/{Context}/Responses/{Action}Response`）。値を返さない操作も空の Response を返す。`App\Http` を参照しない（ModuleBoundaryTest）。
- 他コンテキストの機能は `App\Domain\{Other}\Contracts\` のインターフェースで受ける（LevelUpUseCase は `ItemInventoryInterface`）。

例: `api/app/Domain/Unit/UseCases/LevelUpUseCase.php`

```php
public function handle(int $sysPlayerId, int $trxUnitId, string $mstItemId, int $useCount): LevelUpResponse
{
    $this->validation($sysPlayerId, $trxUnitId, $mstItemId, $useCount);

    return $this->executeWithTransaction(function () use ($sysPlayerId, $trxUnitId, $mstItemId, $useCount) {
        // Service / Repository を呼ぶ
        return new LevelUpResponse(/* ... */);
    });
}
```

## Service

- `api/app/Domain/{Context}/Services/`。依存は Repository や他コンテキストの Contracts をコンストラクタで `private readonly` 注入する。
- ServiceLayerTest が検査すること: クラス名が `Service` で終わる（パッケージの interface / 基底を実装するクラスは除く）、Service から Eloquent の `save()` などを直接呼ばない。
- パッケージの基底クラスを継承して固有部分だけ書く形がある: `UnitLevelService extends NexusLevel\Services\_BaseLevelService implements UnitExperienceInterface`（`api/app/Domain/Unit/Services/UnitLevelService.php`）。
- パッケージの interface を実装してアプリ側の Service に委譲するクラスは `*Adapter` にする（`api/app/Domain/Item/Services/ItemGranterAdapter.php`）。
- 配布の種類を増やすときは `ResourceDeliveryHandlerInterface`（`supports()` / `handle()`）を実装した Handler を `Domain/{Context}/Handlers/` に置く（`api/app/Domain/Album/Handlers/AlbumRecordingDeliveryHandler.php`）。

## Repository

- リレーション（`$trxMailbox->mstMailbox` など）を使うときは、Repository で `with()` して先にまとめて読む。複数件の一覧でリレーションを遅延読み込みすると1件ごとに SELECT が走る（N+1）ので、本番以外では `Model::preventLazyLoading()`（`AppServiceProvider`）が例外にする。リレーション経由の `->where()->first()` を、ループの中で呼ばない。

- `api/app/Repositories/{Sys,Mst,Trx,Log}/`、基底は同じディレクトリの `_BaseXxxRepository`（中身は `packages/nexus-core/src/Repositories/`）。`$modelClass` を指定する。
- 読み取りは `select*`。モデルの集合は `Nexus\Core\Support\CustomCollection` で返す。
- Trx は `queryOrMemory()` がログイン中プレイヤーの行を一度だけ読んでメモリに持ち、以降はそこから引く（`selectById()` は `queryOrMemory()` → `findCachedModel()`）。
- 絞り込みキーは Model の `$selectKeys`、ユニークキーは Model の `$primaryKey` が持つ。Repository 側で再宣言しない（RepositoryKeyDeclarationTest）。
- Sys は全件取得しない（NoFullTableSelectOnSysTest。`_BaseSysRepository::selectAll()` は例外）。
- 書き込みはモデルを `setXxx()` で変えて `setModel($model)` でキューに積む。論理削除は `softDeleteModel()`（`is_delete=true`、trx のみ）、物理削除は `hardDeleteModel()`。実行は UseCase のトランザクション終了時。業務単位のメソッド（`insertXxx()` / `upgradeGrade()` など）は Repository に書き、その中で `setModel()` を呼ぶ。
- 基底に `cacheRemember()` / `newQuery()` / `insert()` / `update()` / `delete()` は無い。
- パッケージの Repository interface を実装するクラスは `*RepositoryAdapter`。Model→DTO 変換は自分で書かず `App\Adapters\{Context}\*Adapter` に任せる（ConverterLocationTest）。例: `api/app/Repositories/Sys/GuildRepositoryAdapter.php` → `api/app/Adapters/Guild/GuildAdapter.php`。

例: `api/app/Repositories/Trx/TrxUnitRepository.php`

## Model

- 列の読み書きは、モデルの型付き getter / setter を使う。`getAttribute('x')` / `setAttribute('x', ...)` を呼んでよいのは、モデル自身（getter / setter の実装）と、列名が動的になる基盤（`packages/nexus-core` の Repository / UnitOfWork / Pitr）だけ。`$model->free_amount` のようなプロパティでの読み書きも同じ。それ以外は PHPStan の `NoGetAttributeOutsideModelsRule` / `NoModelColumnPropertyAccessRule`（`api/phpstan/Rules/`）が落とす。リレーション（`$sysFriendApply->sendPlayer`）は対象外。足りない getter はモデルに追加する。

- `api/app/Models/{Sys,Mst,Trx,Log}/`、`_BaseSys` / `_BaseMst` / `_BaseTrx` / `_BaseLog` を継承する（App側の基底は nexus-core の基底にシャード解決を足したもの。共通処理は `packages/nexus-core/src/Models/_BaseModel.php`）。
- `$table` を書く。`$connection` は書かない（基底が持つ。Trx / Log は null にしてシャードを自動解決する）。
- テーブルに実在するカラムだけを持つ。`$fillable` / `$casts` / `@property` に存在しない列を書かない。リレーションは定義（`trxPlayer(): BelongsTo`）までにして、それを返す getter（`getPlayer()` など）を作らない。
- カラムごとに `getXxx()` / `setXxx()` を書き、中身は `getAttribute()` / `setAttribute()`。
- 日時は `'Y-m-d H:i:s'` の文字列のまま持つ。`$casts` に datetime / date を書かない。`_BaseModel::getDates()` は `[]` を返す。Carbon が要るときは `getDateAttribute()`、文字列は `getDateAttributeString()`。
- 複合主キーは `$primaryKey` を配列にし、`CompositePrimaryKeyTrait` と `$incrementing = false`（`api/app/Models/Trx/TrxItem.php`）。
- Mst の `$fillable` は `deploy_key` から始める。
- `save()` / `update()` / `delete()` / `forceDelete()`（`create()` も `save()` を通る）は `DirectWriteNotAllowedException`、Mst は `MasterDataReadOnlyException`。NoDirectEloquentWriteTest が静的にも検出する。書き込みは Repository 経由。
- `toArray()` と `toResponseArray()` の使い分け: Model の `toArray()` は DB の列をそのまま出す内部用（ログ・デバッグなど）で、API のレスポンスには使わない。API に出す配列は必ず `toResponseArray()`（日付の ISO8601 化と内部列の除外をする）。Response / DTO の `toArray()` は Laravel の `Arrayable` の契約で、API の形そのものを返す。Response の中で Model を配列にするときは `toResponseArray()` を呼ぶ。
- レスポンス用の配列は `toResponseArray()`。基底が `sys_player_id` / `uuid` / `created_at` / `updated_at` を除く。`id` を `{テーブル名}_id` に変えるときはオーバーライドする（`TrxUnit::toResponseArray()`）。
- 定数は Model に置かず `Domain/{Context}/Constants/` かパッケージの `Constants/` に置く。
- 多言語は l10n。`<親>__l10n` テーブルにもモデルを作り、親から `l10n(): HasMany` で引く（`api/app/Models/Mst/MstMessageL10n.php`）。i18n は使わない。

例: `api/app/Models/Trx/TrxUnit.php`

## Response

- 「この処理で何が更新されたか」を返すときは、`UpdatedResources`（`Nexus\Core\UnitOfWork\UpdatedResources`）を使う。UseCase は `executeWithTransaction()` の後に `$this->pullUpdatedResources()` で取り出し、Response の `updated_resources`（`toResponseArray()`: `trx_unit_list`、`trx_item_list` など、更新されたものだけ）に入れる。見本は `Equipment\UseCases\LevelUpUseCase`。対象は `ReturnedAsUpdatedResource` を実装したモデルだけ（許可リスト）で、いまは `sys_player`、`trx_item`、`trx_unit`、`trx_equipment`、`trx_wallet`、`trx_diamond`、`trx_stamina`。画面に出る状態に限り、内訳（`trx_wallet_balance` など）・履歴・ログは付けない。増減するときは `api/tests/Architecture/UpdatedResourceModelsTest.php` の `EXPECTED` を直す。1行しかない `sys_player` は、配列（`_list`）ではなくオブジェクト1件（`sys_player`）で返す（`ReturnedAsSingleUpdatedResource`。Home と同じ形）。物理削除は含まれない。相対更新（`free_amount + 5`）の値は、同時に別の処理が同じ行を更新していると、DBの値とずれることがある

- UseCase ごとに1つ作る: `api/app/Domain/{Context}/Responses/{Action}Response.php`（例: `Chat/UseCases/SendMessageUseCase` → `Chat/Responses/SendMessageResponse`）。`App\Domain\_BaseResponse` を継承する。UseCase の `handle()` がこれを返す。
- HTTP には依存しない。JSON への変換は `_BaseController::execute()` が `toArray()` から行う。
- 複数の UseCase で共用しない。返す形が同じなら、親を継承した子を UseCase ごとに置く（`Auth/TransferByCodeResponse extends SignInResponse`、`Chat/FriendRoomResponse extends RoomResponse`）。親は `final public function __construct` と `from(): static` にする。`handle()` を親クラスから受け継ぐ UseCase は親の Response を返す（Buy 系 → `InAppPurchase/BuyResponse`）。
- UseCase では、整形済みの値が揃っていれば `new XxxResponse(...)`、パッケージのDTO・モデル・コレクションから変換するなら `XxxResponse::from(...)` で作る。生成メソッドは `from()` だけで、引数名は中身に合わせ（`FriendApply $friendApply`）、表示に要る値があれば引数を足してよい（`Friend/ListResponse::from($sysFriendApplyCollection, $sysPlayerId)`）。
- `executeWithTransaction()` の結果は一度変数に受けてから Response にする。`return XxxResponse::from($this->executeWithTransaction(...))` のように入れ子にしない。

```php
$guildApply = $this->executeWithTransaction(fn () => $this->guildService->sendApply($guildId, $sysPlayerId));

return ApplySendResponse::from($guildApply);
```
- 以上は `api/tests/Architecture/ResponsePerActionTest.php` が検査する。
- `toArray()` に snake_case のキーを手で書く。`Responsable` / `toResponse()` や Reflection による自動変換は無い。
- モデルを含めるときは `toResponseArray()` を呼ぶ。Model の `toArray()` は呼ばない（DTO の `toArray()` は可）（`api/app/Domain/Login/Responses/HomeResponse.php`）。
- エラーは Response で返さない。例外を投げれば `_BaseController` が `Nexus\Core\ValueObjects\ErrorResponse` にする。

例: `api/app/Domain/Unit/Responses/LevelUpResponse.php`（`LevelUpUseCase` が `new LevelUpResponse(...)` で返す）

```php
public function __construct(
    public readonly bool $isLeveledUp,
    public readonly int $beforeLevel,
    /* ... */
) {}

public function toArray(): array
{
    return ['is_leveled_up' => $this->isLeveledUp, 'before_level' => $this->beforeLevel, /* ... */];
}
```

## DTO / Result

- UseCase の戻り値は Response（上の節）。`Domain/{Context}/DataTransferObjects/` には、Service と UseCase の間で受け渡す値だけを置く（`api/app/Domain/Auth/DataTransferObjects/SignInResult.php`）。`public readonly` のコンストラクタプロモーションだけで、メソッドを持たない。
- パッケージのDTOは `packages/*/src/DataTransferObjects/` に置き、サフィックスを付けない（DataTransferObjectNamingTest）。`private readonly` + `getXxx()` で書く（`packages/nexus-guild/src/DataTransferObjects/Guild.php`）。
- Model からパッケージDTOへの変換は `App\Adapters\{Context}\{Dto}Adapter::toDto()` / `toDtoArray()`。

## 時刻（ClockUtility）

現在時刻の取得と日時文字列のパースは必ず `Nexus\Core\Utilities\ClockUtility`（`packages/nexus-core/src/Utilities/ClockUtility.php`）を通す。`CarbonImmutable::now()` / `Carbon::now()` / `CarbonImmutable::parse()` / `now()` を直接呼ばない（アプリもテストも）。戻り値の CarbonImmutable から `addDay()` などで派生させるのは構わない。日時は `'Y-m-d H:i:s'` の文字列で扱う。

| 用途 | メソッド |
|---|---|
| 現在時刻 | `now()`（CarbonImmutable）, `nowToString()` |
| パース | `parse(string)` |
| 現在時刻との比較 | `isPast(?string)`, `isFuture(?string)`, `isWithin(?string $startAt, ?string $endAt)`（文字列比較、null は下限・上限なし）, `greaterThanOrEqual(string)`（NOW >= 指定）, `lessThanOrEqual(string)`（NOW <= 指定） |
| ゲーム内日付 | `calcGameDayStart(?string)`, `isSameGameDay(string, string)`, `isToday(string)`。日付の境界は `config('nexus-core.day_start_time')`（`calcDayStartTime()`） |
| 差分（現在 − 指定） | `diffInSeconds()`, `diffInMinutes()`, `diffInHours()`, `diffInDays()` |
| 暦 | `weekOfYear()` と `isoWeekYear()` を組で使う（年末年始の週は暦年とずれる）, `month()`, `year()` |
| テスト用 | `setNow(string)`, `reset()`, `setDayStartTime(string)`, `initialize()` |

`isAfter()` / `isBefore()` / `getGameDayStart()` は無い。

## 例外の投げ方

- アプリの業務エラーは `App\Exceptions\GameException(int $errorCode, string $message)`。コードは `GameErrorCode` の定数。
- 種類別のサブクラス `MasterDataException` / `TransactionDataException` / `SystemDataException` / `BusinessLogicException` に static ファクトリがあるので、該当するものはそれを使う（`throw MasterDataException::item($mstItemId);`、`throw BusinessLogicException::itemNotEnough(...)`）。無ければ `throw new GameException(GameErrorCode::XXX, '...')`。
- 投げる場所は主に UseCase の `validation()` と Service。Controller では捕まえない。
- パッケージは `GameErrorCode` を知らないので、パッケージ固有の例外（`NexusFriend\Exceptions\FriendException` の `CODE_*` 定数や `GuildException::guildNotFound()` などのファクトリ）を投げる。UseCase 側で `Domain/{Context}/Support/*ExceptionTranslator::translate(fn () => ...)` に包んで GameException に変換する（`api/app/Domain/Friend/Support/FriendExceptionTranslator.php`）。
- エラーコードの番号体系と HTTP 299 は architecture.md / api.md。

## テストの書き方

- 置き場所: `api/tests/{Unit,Feature,Architecture}/`、パッケージは `packages/*/tests/`（`api/phpunit.xml` の Packages スイート）。
- PHPUnit のクラス形式で書く（Pest の関数形式は使っていない）。メソッドは `#[Test]` 属性 + 日本語名が多数派。
- api 側の基底は `Tests\TestCase`（`api/tests/TestCase.php`）。setUp で署名検証ミドルウェアを外し、`_BaseMst::allowWrites()` と `_BaseModel::allowDirectWrites()` でフィクスチャの直接投入を許し、`ClockUtility::reset()` する。
- DBを使うテストは `use Tests\RefreshMultipleDatabases;`。sys / mst / trxN を各テストでトランザクションに包んでロールバックする。UseCase を通すテストは UseCase が自分でトランザクションを張るので、`beginDatabaseTransaction()` を空でオーバーライドし、作った行を tearDown で消す（`api/tests/Feature/Notification/NotificationEndpointTest.php`）。
- パッケージのユニットテストは `PHPUnit\Framework\TestCase` を直接継承し、Repository interface の Fake 実装を差し込む（`packages/nexus-friend/tests/Unit/Services/FriendServiceTest.php`）。
- `Tests\TestCase` のヘルパー: `signUpPlayer()` と `authHeaders($token)`（エンドポイントを通すとき）、`useSessionPlayer($sysPlayerId)`（シャード割り当て + セッション差し替え）、`playerConnection()` / `playerLogConnection()`（フィクスチャを入れる接続名）、`flushQueue()`（UseCase を通さず Repository を検証するときにキューを反映）、`refreshMstCache()`（マスターを作った後）、`nonExistentId()`（存在しないIDを決め打ちしない）。
- セッションを自分で差し替えるときは `ApiSession::clearForTest()` してから `ApiSession::setSysPlayerId()`（`api/tests/Unit/Domain/Login/Services/LoginBonusServiceTest.php`）。
- 時刻は `ClockUtility::setNow('2026-03-15 12:00:00')` で固定する。日付の境界に依存するテストは `ClockUtility::setDayStartTime('00:00:00')` も明示する（LoginBonusServiceTest）。
- 呼び出し元の無い関数・メソッドも削除しない。テストで挙動を固定する（`MasterDataException::generic()` / `playerLevel()` はアプリから呼ばれていないが `api/tests/Unit/Exceptions/MasterDataExceptionTest.php` で固定している）。
- アーキテクチャテストの一覧は architecture.md。
