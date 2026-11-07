# アーキテクチャ

新しいAPI・コンテキスト・パッケージを追加するとき、コンテキストやパッケージをまたぐ依存を書くとき、トランザクションや例外の流れを追うときに読む。
各クラスの書き方は [coding-standards.md](./coding-standards.md)、命名は [naming-conventions.md](./naming-conventions.md)、DB規約は [database.md](./database.md)、APIの入出力形式は [api.md](./api.md)。

## 1. 全体像

```
tool/ (運営ツール, Laravel)          api/ (ゲームAPI, Laravel)
  │ packages を使わない              │ composer の path リポジトリで packages/* を読み込む
  │                                  ▼
  │                         packages/nexus-* (汎用実装。App\ を参照しない)
  ▼                                  │
 admin / tool DB ・ mst / sys / trxN DB ◀┘
```

- `api/` … クライアント向けAPI。ゲーム固有の処理は `api/app` に置く
- `packages/` … 他タイトルへ持ち出せる汎用実装。基盤（`nexus-core*`, `nexus-tidb`, `nexus-maintenance`）とドメイン（`nexus-guild`, `nexus-vip` など）に分かれる
- `tool/` … 運営ツール。packages には依存せず、自前のモデル・Repository で各DBに接続する。詳細は [tool.md](./tool.md)

## 2. リクエストの流れ

`POST /api/unit/level_up` を例にたどる。

```
Middleware        ResolveLanguage, CheckMasterHash（api グループ全体）
                  → maintenance → auth.token → idempotency（routes/api.php のグループ）
Controller        App\Http\Controllers\UnitController::levelUp
UseCase           App\Domain\Unit\UseCases\LevelUpUseCase::exec
Service           App\Domain\Unit\Services\UnitLevelService / Item\Contracts\ItemInventoryInterface
Repository        App\Repositories\Trx\TrxUnitRepository::setModel
QueryManager      Nexus\Core\UnitOfWork\Persistence\QueryManager（コミット直前に一括実行）
```

1. `auth.token`（`NexusSecurity\Middleware\VerifyAccessToken`）がトークンを検証し、`ApiSession` にプレイヤーIDを載せる（8節）
2. Controller は `_BaseController::execute()` のコールバック内で、Request から値を取り出して UseCase の `handle()` にスカラーで渡し、その結果をそのまま返す。`handle()` は自分専用の Response（`App\Domain\Unit\Responses\LevelUpResponse` など）を API の形に整形して返し、`execute()` が JSON にする
3. UseCase は `validation()` で事前チェックしてから `executeWithTransaction()` 内で Service を呼ぶ。戻り値は `Domain/Unit/Responses/LevelUpResponse`
4. Service は Repository の `selectById()` で読み、`setModel()` で変更をキューに積む
5. callback が返ると QueryManager がキューを一括実行し、全接続をコミットする（7節）
6. 例外は `execute()` が捕まえてエラーレスポンスにする（9節）

### Responseの合成はControllerの責務

ServiceもUseCaseもResponseを返さない。UseCaseの戻り値は次のいずれか。

- パッケージのDTO、またはその配列（例: `NexusGuild\DataTransferObjects\GuildApply`）
- Eloquentモデル、またはそのCollection（既存モデルで足りる場合）
- 複数の値を返す場合は `app/Domain/{Context}/DataTransferObjects/{Name}Result`
- 返す値が無ければ `void`

`app/Domain` から `App\Http` を参照すると `ModuleBoundaryTest` が落ちる。`execute()` はコールバックが `App\Domain\_BaseResponse` 以外を返すと `LogicException` にする。

## 3. ディレクトリ構成

### api/app

```
Adapters/{Context}/       Model → パッケージDTO の変換（static toDto()）
Console/Commands/         artisan コマンド
Domain/
  _BaseUseCase.php
  {Context}/
    UseCases/             *UseCase（入口は handle()）
    Services/             *Service、パッケージ契約を実装する *Adapter（ItemGranterAdapter など）
    Contracts/            他コンテキストへの公開API（4節）
    Responses/            UseCase ごとの *Response（handle() の戻り値。App\Domain\_BaseResponse を継承）
    DataTransferObjects/  Service と UseCase の間で受け渡す値（SignInResult など）
    Constants/            *Const
    Listeners/            ドメインイベントの受け手（5節）
    Support/              *ExceptionTranslator など、コンテキスト内の補助
    Handlers/ Traits/     必要なコンテキストだけ
  Common/Constants/       全コンテキストから参照してよい共有定数
Exceptions/               GameException 系と GameErrorCode / InfraErrorCode
Http/
  Controllers/            _BaseController を継承
  Middleware/             CheckMaintenance, CheckMasterHash, ResolveLanguage, VerifyAdminToken
  Requests/{Context}/     _BaseRequest を継承
Models/{Mst,Sys,Trx,Log}/ Eloquent モデル（基底は nexus-core の _BaseMst 等）
Persistence/ApiSession.php
Providers/AppServiceProvider.php   パッケージ契約・公開APIのバインド、イベント登録
Repositories/{Mst,Sys,Trx,Log}/    *Repository と、パッケージ契約を実装する *RepositoryAdapter
Traits/                   UseCaseTrait, CompositePrimaryKeyTrait, RequiresAuthenticationTrait
```

認証などの横断処理（トークン検証・署名検証・レート制限・冪等性）は `packages/nexus-core-security` の Middleware が担い、`api/bootstrap/app.php` でエイリアス登録している。

### packages/nexus-*/src

パッケージごとに必要なものだけを持つ: `Services/`、`Repositories/` か `Contracts/`（api 側に実装させる契約、6節）、`DataTransferObjects/`、`ValueObjects/`、`Events/`（5節）、`Exceptions/`、`*ServiceProvider.php`。

基盤パッケージの役割:

| パッケージ | 役割 |
|---|---|
| `nexus-core` | Model/Repository の基底（`_BaseModel`, `_BaseTrxRepository` 等）、`CustomCollection`、`ClockUtility`、`ErrorResponse` |
| `nexus-core` の `UnitOfWork`（`Nexus\Core\UnitOfWork\`） | `QueryManager`、`UsesUnitOfWork`、`PlayerSessionResolverInterface` |
| `nexus-core-security` | 認証・署名・レート制限・冪等性の Middleware、Slack通知 |
| `nexus-core-auth` / `nexus-core-billing` | トークン発行・検証 / App Store・Google Play の課金連携 |
| `nexus-core` の `Pitr`（`Nexus\Core\Pitr\`） | trx の変更ログ（PITR）、`ShardMapper`、`pitr:migrate` |
| `nexus-tidb` | TiDBモードの切り替え（`TidbMode`） |
| `nexus-core` の `ResourceDelivery`（`Nexus\Core\ResourceDelivery\`） | 報酬配布。`ResourceDeliveryService` に `ResourceDeliveryHandlerInterface` 実装を登録する。通貨の付与は `CurrencyGranterInterface`（実装は nexus-wallet） |

## 4. モジュール境界と公開API

`app/Domain/{Context}` と `packages/nexus-*` をそれぞれモジュールとみなし、境界をまたぐ依存を `ModuleBoundaryTest` で検出する。

- コンテキストは他コンテキストの `App\Domain\{Other}\Contracts\` だけを参照できる。`Services/` などの内部は参照しない。例外は共有領域の `Common`
- `app/Domain` から `App\Http` を参照しない
- ドメインパッケージ（基盤パッケージ以外）から `Illuminate\Database` / `Illuminate\Support\Facades` / `Illuminate\Http` を参照しない
- 既存違反の許可リスト（ベースライン）は現在すべて空

他のコンテキストの処理をその場で呼ぶ必要があるとき（結果や「足りない」例外が要るとき）は、相手が `Contracts/` に置いたインターフェースを使う。

| 公開API | 実装 | 使う側 |
|---|---|---|
| `Item\Contracts\ItemInventoryInterface` | `ItemService` | 装備・ユニットの強化、ガチャ・交換所のコスト、課金パック |
| `InAppPurchase\Contracts\DiamondBalanceInterface` | `InAppPurchaseDiamondBalanceService` | ガチャ・交換所のコスト |
| `Sharding\Contracts\ShardAssignerInterface` | `ShardAssignmentService` | サインアップ |
| `Equipment\Contracts\EquipmentExperienceInterface` | `EquipmentLevelService` | 経験値の配布 |
| `Unit\Contracts\UnitExperienceInterface` | `UnitLevelService` | 経験値の配布 |

- 公開APIには、他のモジュールが実際に使うメソッドだけを載せる
- 実装との対応は `AppServiceProvider::register()` で `bind` する
- 結果を待たずに「起きたこと」を伝えるだけなら、次のドメインイベントを使う

## 5. ドメインイベント

あるコンテキストの出来事に別のコンテキストが反応するときは、直接Serviceを呼ばずにドメインイベントでつなぐ。発行側は受け手を知らないので、受け手を増やしても発行側を変えずに済む。

- イベントクラスは発行側のパッケージの `Events/` に置き、`public readonly` の値だけを持つ（例: `NexusLevel\Events\PlayerLeveledUp`）
- 発行側は `Illuminate\Contracts\Events\Dispatcher` をコンストラクタで受け取り `dispatch()` する。`event()` ヘルパーやFacadeは使わない（パッケージのテストで差し替えられなくなる）
- 受け手は受け手側コンテキストの `app/Domain/{Context}/Listeners/` に置き、`AppServiceProvider::boot()` で `Event::listen()` する
- 受け手は同期で処理する（`ShouldQueue` にしない）。発行元と同じUnitOfWorkに書き込み、同じトランザクションで確定させるため
- 例外は**ログイン中プレイヤー以外の行を書く**受け手（通知の受け手など）。相手のシャードは発行元のトランザクションに含まれないため、受け手は同期で受けて、書き込みは `afterCommit()` を付けたキューのジョブへ渡す（`app/Domain/Notification/Listeners/` → `app/Domain/Notification/Jobs/`）。ロールバックされた操作の通知は送られない。相手のシャードは `ApiSession::resolveConnectionNameForPlayer()` で引く

```php
// packages/nexus-level/src/Services/PlayerLevelService.php（発行側）
$this->events?->dispatch(new PlayerLeveledUp($sysPlayerId, $beforeLevel, $afterLevel));

// api/app/Domain/Stamina/Listeners/RecoverStaminaOnPlayerLevelUp.php（受け手）
public function handle(PlayerLeveledUp $event): void { ... }
```

現在の発行元は `PlayerLeveledUp`（nexus-level）、`VipLevelUpEvent`（nexus-vip）、`UnitLeveledUp`（nexus-core の `Resource`。`UnitLevelService::onLevelUp()` が発行し、`Mission\Listeners\RecordMissionProgressOnUnitLevelUp` がミッションの進捗として数える）、`FriendApplySent` / `FriendApplyAccepted`（nexus-friend）、`GuildApplySent` / `GuildApplyAccepted` / `GuildApplyRejected` / `GuildMemberLeft`（nexus-guild）。フレンド・ギルドのイベントは `Notification\Listeners\NotifyOn*` が相手への通知にする。

## 6. パッケージとアプリの境界

packages から `App\` を参照しない（`PackageIndependenceTest`）。永続化などアプリに依存する処理は、パッケージ側がインターフェースを定義し、api 側が実装してコンテナでバインドする。

```
packages/nexus-guild/src/Repositories/GuildRepositoryInterface      ← 契約（パッケージ）
api/app/Repositories/Sys/GuildRepositoryAdapter                      ← 実装（Sys/Trx/Mst の該当ディレクトリ）
api/app/Adapters/Guild/GuildAdapter::toDto()                         ← Model → パッケージDTO の変換
api/app/Providers/AppServiceProvider                                 ← bind(契約, 実装)
```

- 契約の置き場所はパッケージにより `Repositories/*RepositoryInterface` か `Contracts/*Interface`
- Repository 以外の契約（`Nexus\Core\ResourceDelivery\Contracts\ItemGranterInterface` など）の実装は `app/Domain/{Context}/Services/*Adapter` に置く
- `*RepositoryAdapter` の中に private/protected の変換メソッド（`*toDto*` / `Convert*`）を書かず、`App\Adapters` に委譲する（`ConverterLocationTest`）。`App\Adapters` 配下のクラスは `public static function toDto(` を持つ
- パッケージ例外はゲーム固有のエラーコードを知らない。api 側で変換する（9節）

### パッケージの層

パッケージはなるべく着脱できるようにする。パッケージを3つの層に分け、依存してよい向きを決める（`PackageLayerTest`）。

| 層 | パッケージ | 依存してよいもの |
|---|---|---|
| foundation（土台） | nexus-core（リソース `Resource`、報酬配布 `ResourceDelivery`、書き込みの仕組み `UnitOfWork`、変更ログ `Pitr` を含む）、nexus-core-security、nexus-core-auth、nexus-tidb、nexus-wallet | foundation |
| platform（ゲーム共通の部品） | nexus-notification、nexus-mailbox | foundation、platform（循環なし） |
| feature（機能） | 上記以外（gacha、trade、mission、login、reward-track、chat、event、friend、guild、vip など） | foundation、platform。**feature 同士は依存しない** |

- アプリが `nexus-core`（foundation）に依存するのは前提。着脱の対象は、foundation 以外
- feature は、他の feature が壊れずに外せる。feature 同士でやり取りが要るときは、消費する側のパッケージに契約（インターフェース）を定義し、api/app の Adapter で実装する（`TradeCostPayerInterface` が見本）。起きたことの通知は、ドメインイベント（5節）
- どのタイトルでも外せない仕組みは、`nexus-core` に含める。報酬（リソース）を配る仕組み（`Nexus\Core\Resource\`、`Nexus\Core\ResourceDelivery\`）、書き込みをまとめて実行する仕組み（`Nexus\Core\UnitOfWork\`）、trx の変更ログ（`Nexus\Core\Pitr\`）がそれにあたる。通貨の付与は、`nexus-core` が契約 `CurrencyGranterInterface` を定義し、`nexus-wallet` が実装する（`nexus-core` が `nexus-wallet` に依存すると循環し、`nexus-wallet` を外すと `nexus-core` が壊れるため）
- platform は、feature が使う共通の部品（通知、メールボックス）。一緒に入れる前提で、feature が依存してよい
- 使っているパッケージは、必ず `composer.json` の `require` に宣言する（`"nexus/<名前>": "@dev"`）。単体で入れたときに動くようにするため。宣言のずれと循環は `PackageLayerTest` が検出する
- 新しいパッケージを足したら、`PackageLayerTest` の `LAYERS` に層を書く

### イベントの期間の上書き

`mst_event_content` に登録されたコンテンツ（ガチャ・ミッション・交換所・ログインボーナス・リワードトラック・ランキング）は、自身の `start_at` / `end_at` ではなく、イベントの期間で判定される。設計は `docs/event_platform_design.md`。

- 各機能のパッケージは `nexus-event` を知らない。api 側が、マスターを読む境界（`*RepositoryAdapter` など）で、`NexusEvent\Services\EventPeriodResolver` を使って期間を差し替えて返す。パッケージの判定ロジックは変えない
- DTO を返す機能（ミッション・交換所）は `withPeriod()` で複製する。配列を返す機能（リワードトラック）は、行の `start_at` / `end_at` を書き換える。ガチャは `GachaValidationService::validateGachaPeriod()` が、判定の入口で使う
- 子の `end_at` に使う時刻は、種別で決まる（`NexusEvent\Constants\EventContentType::usesExchangeEndAsEndAt()`）。交換所とミッションは `exchange_end_at`、それ以外はイベントの `end_at`
- コンテンツがイベントに入っていなければ（`resolve()` が `null`）、今までどおり自身の期間で判定する。ログインボーナスの通常（`daily`）は、入っていなければ期間を見ない
- 期間を SQL の `where` で絞らない。イベントの期間は SQL では表せないため、上書きした後に PHP（`ClockUtility::isWithin()`）で判定する
- 新しい機能の期間をイベントで上書きしたいときは、`EventContentType` に種別を足し、その機能のマスター読み取りで上書きして、`tests/Feature/Event/EventPeriodOverrideTest.php` にテストを足す

## 7. UnitOfWork とトランザクション

書き込みは即時に実行せず、Repository がキューに積み、UseCase のトランザクション終盤で一括実行する。

- 書き込みは Repository の `setModel()` / `softDeleteModel()` / `hardDeleteModel()` だけ。`app/Repositories/{Trx,Sys,Log}/_Base*Repository` は `Nexus\Core\UnitOfWork\Traits\UsesUnitOfWork` を使い、初回の書き込みで自身を QueryManager に登録する
- Model の `save()` / `update()` / `delete()` / `forceDelete()` は `_BaseModel` が `DirectWriteNotAllowedException` で拒否する（テスト中は許可）。Mst は読み取り専用で `MasterDataReadOnlyException`
- QueryManager、ApiSession、Trx/Sys/Log の Repository は `scoped` バインドで、リクエスト内では同じキュー・キャッシュを共有する

`App\Traits\UseCaseTrait::executeWithTransaction()`（`_BaseUseCase` が use）の流れ:

```
1. sys と全シャード trx1..N で beginTransaction（N は config('database.pitr.active_trx_connections')）
2. callback 実行（読み取り + Repository へのキューイング）
3. QueryManager::flush()       … INSERT / UPDATE / DELETE をバッチ実行し、trx の変更ログ（PITR）を書く
4. QueryManager::execAllLogs() … log_* と課金ログを同じトランザクションで INSERT
5. 全接続を commit。どこかで例外が出たら全接続を rollBack して再送出
```

- トランザクションは callback より前に開始する。callback 内の読み取りもトランザクション内で行われる
- log_* テーブルは対応する trxN と同じDBに入る（`Nexus\Core\Pitr\Logger\ShardMapper::resolveLogConnection()`）。独立した log 接続は無い
- sys_player だけ個別INSERTで採番し、他はバッチINSERT
- INSERT で採番されたIDを callback 内で直後に使う必要があるときだけ `flushQueue()` でキューを先に流す
- 読み取りだけの UseCase は `executeWithTransaction()` を使わなくてよい

## 8. ApiSession

`api/app/Persistence/ApiSession.php`。リクエストで確定したコンテキストを持つ scoped なクラスで、`NexusSecurity\Contracts\PlayerSessionInterface` と `Nexus\Core\UnitOfWork\Contracts\PlayerSessionResolverInterface` の実装としてもバインドされている。

- static メソッド: `getSysPlayerId()` / `hasSysPlayerId()`（未設定で get すると `RuntimeException`）、`getNow()`（プレイヤーID設定時に `ClockUtility::now()` で固定した `CarbonImmutable`）、`getLanguage()`、`resolveConnectionName()`（`trx{node_no}`）、`clearForTest()`
- 値を入れるのは Middleware（`VerifyAccessToken` がプレイヤーIDと時刻、`ResolveLanguage` が Accept-Language から言語）
- Controller は `ApiSession` をコンストラクタで受け取り、`execute()` のコールバック内で `getSysPlayerId()` を読んで UseCase に渡す
- Trx Repository はプレイヤーIDと接続先を ApiSession から自分で解決する（`App\Repositories\Trx\_BaseTrxRepository::resolveShardConnection()`）。Service のメソッドにプレイヤーIDを持ち回らなくても Repository は動く
- `resolveConnectionName()` は sys_sharding_node_player → sys_sharding_node を引き、Redis `shard:player:{id}` に1時間キャッシュする。TiDBモードでは常に `trx1`。プレイヤーが居ない文脈（コンソール等）では Trx Repository は既定接続を使う。シャーディングの詳細は [database.md](./database.md)

## 9. 例外とエラーコード

### 例外クラス

`api/app/Exceptions/`:

- `GameException(int $errorCode, string $message)` … 業務エラー。サブクラス `MasterDataException` / `TransactionDataException` / `SystemDataException` / `BusinessLogicException` は用途別の名前付きコンストラクタ（`::item()` など）を持つ
- `GameErrorCode` … アプリ層の5桁コード。インフラ層・パッケージ層のコードは別名だけを置く（例: `STAMINA_NOT_ENOUGH = StaminaErrorCode::INSUFFICIENT_STAMINA`）
- `InfraErrorCode` … 3桁以下のインフラ系コード

### エラーコード体系

| 範囲 | 定義場所 |
|---|---|
| 1-999 | `App\Exceptions\InfraErrorCode`（DB 100番台、Redis 200番台、暗号 300番台、データ整合性 400番台…、不明 999） |
| 1000-9999 | 各パッケージの `*ErrorCode`（現在あるのは `WalletErrorCode` 1000番台、`StaminaErrorCode` 1100番台、`ResourceErrorCode` 1900番台） |
| 10000-99999 | `App\Exceptions\GameErrorCode`。コンテキストごとに1000単位（Auth 10000、Player 11000、Unit 12000 … RewardTrack 22000、Mission 23000、汎用 99000）。割り当ては同クラスの冒頭コメント |

### レスポンスへの変換

- `GameException` → HTTP **299** + `{"error_code": int, "message": string}`（`Nexus\Core\ValueObjects\ErrorResponse::businessError()`）。`_BaseController::execute()` が捕まえる。`execute()` の外で投げられた分は `bootstrap/app.php` の `withExceptions` が同じ形で返す
- それ以外の例外 → システムエラー。`getCode()` が100-599ならそれをHTTPステータスに、それ以外は500。`error_code` は `getCode()`（0なら `InfraErrorCode::UNKNOWN_ERROR`）
- 本番（`app.env=production`）ではメッセージを固定文言に置き換える（`maskForProduction()`）
- どちらもログに残し Slack に通知する（除外コードは `AppServiceProvider::boot()` の `security.slack_ignore_error_codes`）

### パッケージ例外の変換

パッケージは種類だけを持つ例外（例: `NexusGuild\Exceptions\GuildException::CODE_GUILD_FULL`）を投げ、api 側の `app/Domain/{Context}/Support/{Context}ExceptionTranslator` が `GameException` に変換する。同じ種類でも操作で返すコードが変わるため、操作ごとに入口を分けている。

```php
// app/Domain/Guild/UseCases/ApplySendUseCase.php
return GuildExceptionTranslator::forApplySend(function () use ($sysPlayerId, $guildId) {
    // nexus-guild の Service 呼び出し
});
```

現在の Translator: Guild, Friend, Chat, RewardTrack, Mission, Trade。

## 10. アーキテクチャテスト

`api/tests/Architecture/`。規約の多くはここで強制している。落ちたらメッセージに直し方が書いてある。

| テスト | 強制していること |
|---|---|
| `ModuleBoundaryTest` | コンテキスト間は `Contracts\` 経由のみ、Domain→Http 禁止、ドメインパッケージから Eloquent/Facade/Http 禁止（4節） |
| `PackageIndependenceTest` | `packages/*/src` から `App\` を参照しない |
| `PackageRegistrationTest` | `packages/*` が api の `composer.json`（path リポジトリと require）・`phpstan.neon`・`phpunit.xml`（カバレッジ）の全てに登録されている |
| `ConverterLocationTest` | `*RepositoryAdapter` に変換メソッドを書かない。`app/Adapters` 配下は `toDto()` を持つ |
| `ServiceLayerTest` | Service での `save()` 直呼び禁止。Service / UseCase / Repository のクラス名サフィックス |
| `NoDirectEloquentWriteTest` | `app/` と `packages/` の本番コードで `$model->save()` / `delete()` / `forceDelete()` を呼ばない |
| `NoFullTableSelectOnSysTest` | Sys Repository で全件取得しない。`_BaseSysRepository::selectAll()` は実行時に例外 |
| `RepositoryKeyDeclarationTest` | キーは Model の `$primaryKey` / `$selectKeys` に一本化し、Repository で再宣言しない |
| `DataTransferObjectNamingTest` | パッケージの DTO にサフィックス（`Dto`/`DTO`）を付けない。ディレクトリ名は `DataTransferObjects` |
| `ResponseKeyNamingTest` | Request/Response の `*_id` キーはテーブル接頭辞付き（`apply_id` は不可）。例外は許可リスト |
| `MigrationNameUniquenessTest` | 同じDB（sys / mst / trx+log）のマイグレーション名が重複しない |
| `Psr4ConsistencyTest` | `api/app` の名前空間とディレクトリの大小文字が一致する |
| `AgentDocsInSyncTest` | `.opencode` が `.claude` へのシンボリックリンクである |

静的解析は PHPStan level 6（`api/phpstan.neon`）。実行方法は [development.md](./development.md)。
