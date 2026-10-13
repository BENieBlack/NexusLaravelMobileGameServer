# 命名規約

クラス・ファイル・変数・メソッド・配列キーに名前を付けるときに読む。クラスの中身の書き方は coding-standards.md、テーブル名・カラム名・値のケースは database.md。

## ケース

| 対象 | ケース | 例 |
|---|---|---|
| テーブル・カラム・配列キー | snake_case | `trx_unit`, `mst_unit_id`, `'is_leveled_up'` |
| 変数・プロパティ・メソッド | camelCase | `$trxUnit`, `$isLeveledUp`, `selectById()` |
| クラス | PascalCase | `TrxUnit`, `LevelUpUseCase` |
| 定数 | UPPER_SNAKE_CASE | `UnitConst::TYPE_ATTACK` |

## ID とモデル変数

- IDを表す変数・引数・キーには由来するDBの接頭辞を付ける: `$mstItemId`（mst_item.id）、`$trxUnitId`（trx_unit.id）、`$sysPlayerId`、`$sysFriendApplyId`。`$itemId` / `$playerId` のように由来の分からない名前にしない。
- DBの主キー列は `id`。Model の中では `id` のまま扱い、変数・Request・Response では `{テーブル名}_id` と呼ぶ（`TrxUnit::toResponseArray()` が `id` → `trx_unit_id` に変える）。
- Request / Response の `*_id` キーは `sys_` / `trx_` / `mst_` / `log_` で始める。ResponseKeyNamingTest が検査し、例外（`my_id`, `device_id`, `sender_sys_player_id` など）はテストの `ALLOWED` にある（`api/tests/Architecture/ResponseKeyNamingTest.php`）。
- 同じテーブルを役割違いで2本持つ列は役割を前に付ける: `sender_sys_player_id` / `receiver_sys_player_id`。
- Eloquent モデルを入れる変数・プロパティもテーブル名で呼ぶ: `$trxUnit`, `$mstItem`, `$sysPlayer`（`api/app/Domain/Unit/UseCases/LevelUpUseCase.php`）。

## bool と変更前後

- bool のカラム・キー・変数は `is_` / `has_` / `needs_` で始める: `is_leveled_up`, `is_delete`, `has_step_up`, `needs_update` / `$isLeveledUp`。Model の getter は `getIsDelete()`（`api/app/Models/Trx/TrxUnit.php`）。
- 変更前後の値は `before_*` / `after_*` の対にする。`old_` / `new_` / `previous_` は使わない。例: `before_level` / `after_level`（`api/app/Http/Responses/Unit/LevelUpResponse.php`、log テーブルの列も同じ）。

## クラス名と置き場所

| 種類 | 置き場所 | 名前 | 例 |
|---|---|---|---|
| Controller | `api/app/Http/Controllers/` | `{Context}Controller` | `UnitController` |
| Request | `api/app/Http/Requests/{Context}/` | `{Action}Request` | `Unit/LevelUpRequest` |
| Response | `api/app/Http/Responses/{Context}/` | `{Action}Response` | `Unit/LevelUpResponse` |
| UseCase | `Domain/{Context}/UseCases/` | `{Action}UseCase` | `Gacha/DrawUseCase`, `InAppPurchase/BuyDiamondUseCase` |
| Service | `Domain/{Context}/Services/` | `{Context}{役割}Service` | `GachaCostService`, `InAppPurchaseDiamondService` |
| パッケージ契約の実装 | `Domain/{Context}/Services/` | `{契約名}Adapter` | `ItemGranterAdapter` |
| UseCase の戻り値 | `Domain/{Context}/DataTransferObjects/` | `{Name}Result` | `LevelUpResult` |
| パッケージDTO | `packages/*/src/DataTransferObjects/` | サフィックスなし | `Guild`, `FriendApply` |
| Model→DTO 変換 | `api/app/Adapters/{Context}/` | `{Dto}Adapter` | `GuildAdapter` |
| 公開API | `Domain/{Context}/Contracts/` | `{Name}Interface` | `ItemInventoryInterface` |
| イベント | `packages/*/src/Events/` | 過去形、サフィックスなし | `PlayerLeveledUp` |
| リスナー | `Domain/{Context}/Listeners/` | `{動作}On{Event}` | `RecoverStaminaOnPlayerLevelUp` |
| 配布ハンドラ | `Domain/{Context}/Handlers/` | `{Name}DeliveryHandler` | `AlbumRecordingDeliveryHandler` |
| 例外の変換 | `Domain/{Context}/Support/` | `{Context}ExceptionTranslator` | `FriendExceptionTranslator` |
| Model | `api/app/Models/{Sys,Mst,Trx,Log}/` | テーブル名の PascalCase | `TrxUnit`, `SysFriendApply`, `MstMessageL10n` |
| Repository | `api/app/Repositories/{Sys,Mst,Trx,Log}/` | `{Model}Repository` | `TrxUnitRepository` |
| Repository（パッケージ契約の実装） | 同上 | `{Name}RepositoryAdapter` | `GuildRepositoryAdapter` |
| Repository の契約 | `packages/*/src/Repositories/` | `{Name}RepositoryInterface` | `FriendApplyRepositoryInterface` |
| 例外 | `api/app/Exceptions/`, `packages/*/src/Exceptions/` | `{種類}Exception` | `MasterDataException`, `FriendException` |
| Utility | `packages/nexus-core/src/Utilities/` | `{Name}Utility` | `ClockUtility` |
| 基底クラス | 各ディレクトリ | `_Base` で始める | `_BaseController`, `_BaseUseCase`, `_BaseTrx` |

- UseCase にはドメイン名を付けない（名前空間で分かり、Controller からしか呼ばれない）。Service には付ける（他コンテキストから注入され、呼び出し側で出どころが分かる必要がある）。ドメイン名だけで役割が明らかなら役割語を省く（`ItemService`, `StaminaService`）。
- ServiceLayerTest がクラス名の末尾を検査する: `Domain/*/Services/` は `Service`（パッケージ契約の実装は除く）、`Domain/*/UseCases/` は `UseCase`、`Repositories/` は `Repository` / `RepositoryInterface` / `RepositoryAdapter`。
- DTO にサフィックス（`Dto` / `DTO`）を付けず、ディレクトリは `DataTransferObjects` にする（DataTransferObjectNamingTest）。
- `Request` は HTTP リクエストのクラス専用。申請を表すテーブル・モデルは `Apply` を使う（`sys_friend_apply` / `SysFriendApply`、`SysGuildApply`）。
- 多言語は l10n で統一し i18n は使わない。テーブル `<親>__l10n`、モデル `{親Model}L10n`、親からのリレーション `l10n()`（`api/app/Models/Mst/MstMessage.php`）。

## ディレクトリ

`api/app/Domain/{Context}/` の下は複数形: `UseCases/`, `Services/`, `Constants/`, `Contracts/`, `DataTransferObjects/`, `Listeners/`, `Support/`, `Handlers/`, `Traits/`。`DTOs/` / `Dto/` は使わない。

## メソッド名

| 場所 | 用途 | 名前 |
|---|---|---|
| Repository | 読み取り | `select*`: `selectById()`, `selectByXxx()`, `selectListByXxx()`, `selectAllByXxx()`, `selectMaxXxx()` |
| Repository | 書き込み（キューに積む） | 基底の `setModel()` / `softDeleteModel()` / `hardDeleteModel()`、業務単位は `insertXxx()` / `updateXxx()` など |
| Service | 取得・計算 | `findXxx()`（`findUnitLevel()`, `findItemAmount()`）、検証は `validateXxx()` |
| Model | カラムの読み書き | `getXxx()` / `setXxx()`（bool 列も `getIsDelete()`） |
| UseCase | 入口・事前検証 | `exec()` / `validation()` |
| Response | 生成 | `fromResult()` / `fromDto()` / `fromDtoArray()` / `fromCollection()` |
| Adapter（変換） | Model→DTO | `toDto()` / `toDtoArray()` |
| ExceptionTranslator | 例外の変換 | `translate()` |

## 定数クラス

- ドメインの定数をまとめるクラスは `{Context}Const`: `UnitConst`, `EquipmentConst`, `StaminaConst`, `InAppPurchaseConst`（`Domain/{Context}/Constants/`）、`BillingConst`（`packages/nexus-core-billing/src/Constants/`）。
- 1種類の値の集合はその種類名にする: `ElementType`, `RarityType`（`Domain/Common/Constants/`）、`FriendStatus`, `GuildRole`（パッケージの `Constants/`）。PHP の enum にする場合も同じ（`Domain/Mailbox/Constants/Category.php`、`packages/nexus-resource/src/Enums/ItemEffectType.php`）。
- 定数名は UPPER_SNAKE_CASE、文字列の値は snake_case（`'player_exp'`）。
