
# データベース設計ドキュメント

## プロジェクト概要

このドキュメントは、Laravelモバイルゲーム拡張プロジェクトのデータベース設計ルールと実装パターンを定義しています。

### 完了済みタスク（2026年2月22日時点）

#### Phase 1: 既存シーダーの整備
- ✅ MstBillingPlatformProductSeeder - truncate処理追加、べき等性確保
- ✅ MstInAppPurchaseSeeder - truncate処理追加、テーブル名修正
- ✅ SysShardingSeeder - truncate処理追加
- ✅ LogAccessSeeder - 動作確認（874,277件生成）
- ✅ LogInAppPurchaseSeeder - 動作確認（14,172件生成）

#### Phase 2: 新規マスターデータシーダー作成
- ✅ MstUnitSeeder - 12ユニット + 24多言語レコード（日本語・英語）
- ✅ MstItemSeeder - 12アイテム + 24多言語レコード（日本語・英語）
- ✅ MstEquipmentSeeder - 12装備 + 24多言語レコード（日本語・英語）

#### Phase 3: 全l10nテーブルの複合主キー化
- ✅ マイグレーション修正（mst_unit__l10n, mst_item__l10n, mst_equipment__l10n）
- ✅ Eloquentモデル修正（MstUnitL10n, MstItemL10n, MstEquipmentL10n）
- ✅ シーダー修正（全3シーダーから`id`指定を削除）
- ✅ DatabaseSeeder統合 - 適切な実行順序で全シーダー呼び出し
- ✅ マイグレーション再実行 - `migrate:fresh`で全テーブル再作成
- ✅ 全シーダー実行テスト - `php artisan db:seed`が正常完了

#### 成果物
```
Master Data (mst):
- 12 units + 24 unit localizations ✅
- 12 items + 24 item localizations ✅
- 12 equipment + 24 equipment localizations ✅
- 10 billing platform products ✅
- 5 in-app purchases + 10 localizations + 2 contents + 3 effects ✅

System Data (sys):
- 1 sharding configuration + 2 nodes ✅

Log Data (log):
- 873,277 access logs ✅
- 14,172 purchase logs ✅
```

### 重要な設計原則

1. **外部キー制約を使用しない** - シャーディング対応、INSERT順序の柔軟性確保
2. **日付カラムは全て`datetime`型** - `timestamp`型は禁止（2038年問題、タイムゾーン問題回避）
3. **l10nテーブルは複合主キー** - `id`カラム不要、親ID + languageで主キー構成
4. **deploy_keyシステム** - mstデータベース全テーブルに必須、デフォルト値202601010
5. **外部キー参照カラムは`{テーブル名}_id`形式** - 例: `sys_player_id`, `mst_unit_id`

---

## ゲーム内用語の統一

### 通貨の呼称

このプロジェクトでは、ゲーム内通貨を以下の用語で統一します：

- **有償ダイアモンド** (Paid Diamond): 課金で購入した有償通貨
- **無償ダイアモンド** (Free Diamond): ゲーム内報酬などで入手した無償通貨

**❌ 使用しない用語:**
- Currency（通貨全般を指す抽象的な用語）
- Gem（宝石）
- Stone（石）
- Crystal（クリスタル）

**注意:**
- `currency_code`（JPY, USD, EUR）は **決済通貨コード** であり、ゲーム内通貨ではないため変更不要
- テーブル名、カラム名、クラス名、変数名は全て `diamond` を使用すること

**例:**
```php
// ✅ 正しい命名
$table->unsignedInteger('diamond_amount')->comment('ダイアモンド数量');
$table->unsignedInteger('paid_diamond')->comment('有償ダイアモンド');
$table->unsignedInteger('free_diamond')->comment('無償ダイアモンド');

class DiamondRepository {}
function addDiamond(int $amount) {}

// ❌ 避けるべき命名
$table->unsignedInteger('gem_amount');
$table->unsignedInteger('stone_count');
class CurrencyRepository {}
```

## テーブル命名規約
- データベースのテーブル名は、単数形で命名する。
  - 複数形にするのがLaravel的にも一般的ですが、単数形の方がわかりやすいと判断したため。
  - fish -> fishのように 単複同形/不可算名詞など、経験上ミスが多く、後から修正することが難しいため
  - 同じカテゴリのテーブルをまとめて管理するため、単数形の方がわかりやすいと判断したため
- mst, trx, log, adm, sys, tolの接頭辞をつける
  - mst: マスターテーブル（mstデータベース）
  - trx: トランザクションテーブル（trx1, trx2データベース - プレイヤーデータ）
  - log: ログテーブル（logデータベース）
  - adm: 管理者用テーブル（admデータベース - アカウント・権限管理）
  - sys: システムテーブル（sysデータベース - デプロイ管理、シャーディング管理、プレイヤーマスター）
  - tol: 運営ツールテーブル（tolデータベース - マスター状況、アセット管理、バナー、キャッシュ制御等）

```sample
mst_mission
mst_mission_reward
mst_unit
mst_unit_skill

trx_player
trx_item
trx_unit

sys_player
sys_sharding
sys_sharding_node
sys_deploy_master

tol_master_status
tol_asset_status
tol_banner
tol_cache_control
tol_maintenance
tol_notice
```

## データベースの役割と設計方針

### sysデータベース: パブリック情報の管理

**重要ポリシー: sysデータベースには「パブリックな情報」を保持する**

sysデータベースは、シャーディングされたtrxデータベース（trx1, trx2）へのアクセスを最小化するために、プレイヤーの公開情報を独立して保持します。

#### sysデータベースに保持すべき情報

以下のような「他のプレイヤーから参照される可能性がある情報」はsysデータベースに保持します：

- **基本情報**
  - プレイヤー名（name）
  - プレイヤーレベル（level）
  - 最終ログイン日時（last_login_at）
  - ログインタイプ（login_type）

- **公開ステータス**
  - 総合戦闘力（total_power）
  - ランク・称号（rank, title）
  - プレイヤーアイコン（icon_id）

- **パーティ・編成情報**
  - 使用中のパーティ編成（party_units）
  - リーダーユニット（leader_unit_id）
  - 装備中のアイテム（equipped_items）

- **ソーシャル関連**
  - フレンドリスト（sys_apply_request）
  - ギルド所属情報（sys_guild_member）
  - プレイヤー同士のマッチング情報

#### 設計上の利点

1. **パフォーマンスの向上**
   ```php
   // ❌ 悪い例: シャード先までSELECTが必要
   $player = TrxPlayer::on($shardConnection)->find($playerId);
   $name = $player->name;
   
   // ✅ 良い例: sysから直接取得
   $player = SysPlayer::find($playerId);
   $name = $player->name;
   ```

2. **クロスシャード検索の効率化**
   ```php
   // ランキングやフレンド一覧など、複数プレイヤーの情報を表示する際に有効
   $topPlayers = SysPlayer::orderBy('total_power', 'desc')->limit(100)->get();
   // シャード先を跨がずに一度のクエリで取得可能
   ```

3. **データの非正規化によるトレードオフ**
   - データは二重管理となるが、読み込み性能を優先
   - trxデータベースが正（マスター）、sysデータベースが写し（スナップショット）
   - 更新時は両方を更新する設計とする

#### 実装例: プレイヤー情報の更新

```php
// プレイヤーレベルアップ時の処理
DB::transaction(function () use ($playerId, $newLevel) {
    // 1. trxデータベースを更新（正式なデータ）
    TrxPlayer::on($shardConnection)
        ->where('sys_player_id', $playerId)
        ->update(['level' => $newLevel]);
    
    // 2. sysデータベースも同時に更新（公開用スナップショット）
    SysPlayer::where('id', $playerId)
        ->update(['level' => $newLevel]);
});
```

#### trxデータベースに保持すべき情報

以下のような「プレイヤー個人のみが参照する情報」はtrxデータベースに保持します：

- 所持アイテムの詳細（trx_item）
- 所持ユニットの詳細（trx_unit）
- クエスト進行状況（trx_quest_progress）
- ガチャ履歴（trx_gacha_history）
- 所持通貨・ポイント（trx_player.gold, trx_player.gem）
- プライベートな設定（通知設定、言語設定など）

### その他のデータベース

- **mst**: ゲームマスターデータ（アイテム定義、キャラクター定義など）
- **log**: ゲームログ（APIアクセス、課金、ガチャなど）
- **adm**: 運営管理者のアカウント・権限管理
- **tol**: 運営ツールの機能データ（バナー、メンテナンス、お知らせなど）


## カラム命名規約

### 基本ルール

- 各テーブルにはからなずidカラムを設ける。
  - **例外**: 複合PRIMARY KEYで一意性を保証できる場合は、idカラムを持たなくてよい（trx_item, trx_device, trx_player_snsなど）
- カラム名は、スネークケースで命名する。
- タイムスタンプカラムは、created_at、updated_atの形式で命名する。
- 論理削除を行う場合は、deleted_atカラムを設ける。
- ブール値のカラムは、is_やhas_などの接頭辞を使用して命名する。

### 外部キー参照カラムの命名規則

**重要: 外部キー相当のカラムは、必ず`{参照先テーブル名}_id`の形式で命名する。**

カラム名から参照先テーブルとカラムが一目で分かるようにすることで、可読性と保守性が向上します。

#### 基本パターン

```php
// ✅ 正しい命名
$table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
$table->unsignedBigInteger('sys_player_device_id')->comment('sys_player_deviceテーブルのID');
$table->unsignedBigInteger('mst_unit_id')->comment('mst_unitテーブルのID');
$table->unsignedBigInteger('mst_item_id')->comment('mst_itemテーブルのID');

// ❌ 誤った命名（参照先が不明瞭）
$table->unsignedBigInteger('player_id')->comment('プレイヤーID');  // どのテーブルのplayer?
$table->unsignedBigInteger('device_id')->comment('デバイスID');    // どのテーブルのdevice?
$table->unsignedBigInteger('item_id')->comment('アイテムID');      // どのテーブルのitem?
```

#### 自テーブルを参照する場合

同じテーブル内で自己参照する場合は、意味を明確にするために接頭辞を付ける。

```php
// sys_friend_apply テーブル
$table->unsignedBigInteger('request_sys_player_id')->comment('リクエスト送信者のsys_playerテーブルのID');
$table->unsignedBigInteger('receive_sys_player_id')->comment('リクエスト受信者のsys_playerテーブルのID');

// ❌ 誤った命名
$table->unsignedBigInteger('from_player_id');  // どのテーブルのplayer?
$table->unsignedBigInteger('to_player_id');    // どのテーブルのplayer?
```

#### 複数のテーブルを参照する場合

同じテーブルから複数の異なるテーブルを参照する場合も、テーブル名を明記する。

```php
// mst_in_app_purchase テーブル
$table->unsignedBigInteger('app_store_product_id')->nullable()->comment('mst_billing_platform_productテーブルのID (AppStore)');
$table->unsignedBigInteger('google_play_product_id')->nullable()->comment('mst_billing_platform_productテーブルのID (GooglePlay)');

// sys_deploy テーブル
$table->unsignedBigInteger('sys_deploy_master_id')->comment('sys_deploy_masterテーブルのID');
$table->unsignedBigInteger('sys_deploy_asset_id')->comment('sys_deploy_assetテーブルのID');
```

#### プラットフォーム固有の識別子

外部プラットフォーム（AppStore, GooglePlayなど）の商品IDは、`platform_`接頭辞を付ける。

```php
// ✅ プラットフォーム側の商品識別子
$table->string('platform_product_id', 255)->comment('プラットフォーム商品ID (com.example.gem100)');

// ✅ ゲーム内テーブルの参照
$table->unsignedBigInteger('mst_billing_platform_product_id')->comment('mst_billing_platform_productテーブルのID');
```

#### 実装例: sys_player_token テーブル

```php
Schema::connection('sys')->create('sys_player_token', function (Blueprint $table) {
    $table->id();
    // ✅ 参照先テーブル名を明記
    $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
    $table->unsignedBigInteger('sys_player_device_id')->comment('sys_player_deviceテーブルのID');
    $table->string('refresh_token_hash', 64)->unique()->comment('リフレッシュトークンのSHA-256ハッシュ');
    $table->dateTime('expires_at')->comment('有効期限（30日）');
    
    $table->index('sys_player_id');
    $table->index('sys_player_device_id');
});
```

### コメントの記述

外部キー相当のカラムには、参照先テーブルを明記したコメントを必ず付ける。

```php
// ✅ 推奨: 参照先を明記
$table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
$table->unsignedBigInteger('mst_unit_id')->comment('mst_unitテーブルのID');

// ✅ より詳細な情報を含める場合
$table->unsignedBigInteger('request_sys_player_id')->comment('リクエスト送信者のsys_playerテーブルのID');

// ⚠️ 最低限必要（できれば上記の形式を推奨）
$table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
```

### 命名規則まとめ

| パターン | 命名形式 | 例 |
|---------|---------|-----|
| 基本の外部キー参照 | `{テーブル名}_id` | `sys_player_id`, `mst_unit_id` |
| 自己参照（役割明示） | `{役割}_{テーブル名}_id` | `request_sys_player_id`, `parent_mst_category_id` |
| プラットフォームID | `{プラットフォーム名}_{テーブル名}_id` | `app_store_product_id`, `google_play_product_id` |
| 外部システムID | `platform_{識別子}_id` | `platform_product_id`, `platform_transaction_id` |

## 多言語対応テーブル（L10nテーブル）

多言語対応が必要なマスターデータは、メインテーブルと多言語テーブルに分割します。

### 命名規則

- **メインテーブル**: 通常の命名規則に従う（例: `mst_in_app_purchase`）
- **多言語テーブル**: メインテーブル名 + `__l10n`（アンダースコア2つ）
  - 例: `mst_in_app_purchase__l10n`
  - **理由**: アンダースコア2つにすることで、テーブル一覧でメインテーブルと多言語テーブルが隣接して表示され、視認性が向上する

### テーブル構造

#### メインテーブル
- 言語に依存しない情報のみを保持
- 例: ID、フラグ、日時、外部キー参照など

#### 多言語テーブル（__l10nテーブル）
- **複合PRIMARY KEY: `[メインテーブルID, language]`**
- **重要: `id`カラムは持たない（単独のPRIMARY KEYは不要）**
- `language`カラム: `config('language.supported')`のEnum型を使用
- 言語依存の情報: name, description など

### 複合主キーの設計理由

**なぜl10nテーブルに`id`カラムを持たないのか？**

1. **一意性の保証**
   - 親テーブルのIDと言語コードの組み合わせで、レコードが一意に特定できる
   - 例: `(mst_unit_id=1, language='ja')`は1つしか存在しない

2. **データベース設計の原則**
   - 複合キーで一意性が保証できる場合、代理キー（surrogate key）としての`id`は不要
   - 単独の`id`カラムは冗長で、データベース設計として不適切

3. **パフォーマンス**
   - 複合PRIMARY KEYにより、`WHERE mst_unit_id = ? AND language = ?`のクエリが最適化される
   - 単独の`id`を使うと、親IDと言語での検索時に追加のインデックスが必要

4. **リレーションの明確化**
   - 親テーブルとの1対多の関係が明確
   - 「1つのユニットに対して、言語ごとに1つのl10nレコード」という構造が直感的

### 実装例

#### マイグレーション

```php
// メインテーブル: mst_unit
Schema::connection('mst')->create('mst_unit', function (Blueprint $table) {
    $table->id()->comment('ユニットID');
    $table->string('category', 50)->comment('ユニットカテゴリ');
    $table->unsignedInteger('rarity')->comment('レアリティ (1-5)');
    $table->unsignedInteger('base_power')->comment('基礎戦闘力');
    $table->boolean('is_active')->default(true)->comment('有効フラグ');
    $table->integer('deploy_key')->default(202601010)->comment('デプロイキー（マスターデータバージョン管理）');
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
    
    $table->index('category');
    $table->index('rarity');
    $table->index('is_active');
    $table->index('deploy_key');
});

// 多言語テーブル: mst_unit__l10n
Schema::connection('mst')->create('mst_unit__l10n', function (Blueprint $table) {
    // ✅ idカラムは不要
    $table->unsignedBigInteger('mst_unit_id')->comment('mst_unitテーブルのID');
    $table->enum('language', config('language.supported'))->comment('言語コード');
    $table->string('name', 255)->comment('ユニット名');
    $table->text('description')->nullable()->comment('ユニット説明');
    $table->text('skill_description')->nullable()->comment('スキル説明');
    $table->integer('deploy_key')->default(202601010)->comment('デプロイキー（マスターデータバージョン管理）');
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
    
    // ✅ 複合PRIMARY KEY（idカラムではない）
    $table->primary(['mst_unit_id', 'language']);
    $table->index('mst_unit_id');
    $table->index('language');
    $table->index('deploy_key');
});
```

#### Eloquentモデル

複合主キーを持つl10nモデルは、特別な実装が必要です。

```php
namespace App\Models\Mst;

class MstUnitL10n extends _BaseMst
{
    protected $table = 'mst_unit__l10n';
    
    // ✅ 複合主キーのため、primaryKeyをnullに設定
    protected $primaryKey = null;
    
    // ✅ 自動インクリメントを無効化
    public $incrementing = false;

    protected $fillable = [
        'deploy_key',
        'mst_unit_id',
        'language',
        'name',
        'description',
        'skill_description',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
    ];

    /**
     * ✅ 複合主キーでのUPDATE/DELETE処理のため、setKeysForSaveQueryをオーバーライド
     */
    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('mst_unit_id', '=', $this->getAttribute('mst_unit_id'))
            ->where('language', '=', $this->getAttribute('language'));
        return $query;
    }

    /**
     * 親テーブルとのリレーション
     */
    public function mstUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MstUnit::class, 'mst_unit_id', 'id');
    }
}
```

#### シーダー

シーダーでl10nレコードを作成する際は、**`id`フィールドを含めない**ことが重要です。

```php
class MstUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('mst')->table('mst_unit')->truncate();
        DB::connection('mst')->table('mst_unit__l10n')->truncate();

        // メインテーブルにユニットを作成
        $warrior = MstUnit::create([
            'deploy_key' => 202601010,
            'category' => 'Fighter',
            'rarity' => 5,
            'base_power' => 1200,
            'is_active' => true,
        ]);

        // ✅ 多言語レコードの作成（idを指定しない）
        MstUnitL10n::create([
            'deploy_key' => 202601010,
            'mst_unit_id' => $warrior->id,  // 親テーブルのID
            'language' => 'ja',             // 言語コード
            'name' => '聖剣の戦士',
            'description' => '伝説の聖剣を扱う勇敢な戦士',
            'skill_description' => '敵全体に光属性の大ダメージ',
        ]);

        MstUnitL10n::create([
            'deploy_key' => 202601010,
            'mst_unit_id' => $warrior->id,
            'language' => 'en',
            'name' => 'Holy Sword Warrior',
            'description' => 'A brave warrior wielding the legendary holy sword',
            'skill_description' => 'Deals massive light damage to all enemies',
        ]);

        // ❌ 以下のような指定は不要（idカラムが存在しないため）
        // MstUnitL10n::create([
        //     'id' => 1,  // ← これは不要
        //     'mst_unit_id' => $warrior->id,
        //     'language' => 'ja',
        //     'name' => '聖剣の戦士',
        // ]);
    }
}
```

### べき等性の確保

シーダーは何度実行しても同じ結果になるよう、`truncate()`を使用します。

```php
public function run(): void
{
    // ✅ べき等性を確保するため、最初にテーブルをクリア
    DB::connection('mst')->table('mst_unit')->truncate();
    DB::connection('mst')->table('mst_unit__l10n')->truncate();
    
    // データ作成処理...
}
```

**注意**: `truncate()`は外部キー制約がある場合にエラーになりますが、このプロジェクトでは外部キー制約を使用しないため問題ありません。

### クエリ例

#### 特定言語のデータ取得

```php
// 日本語のユニット名を取得
$unit = MstUnit::with(['l10n' => function ($query) {
    $query->where('language', 'ja');
}])->find(1);

echo $unit->l10n->first()->name; // "聖剣の戦士"
```

#### 複数言語を一度に取得

```php
// ユニットと全言語の多言語データを取得
$unit = MstUnit::with('l10n')->find(1);

foreach ($unit->l10n as $localization) {
    echo "{$localization->language}: {$localization->name}\n";
}
// 出力:
// ja: 聖剣の戦士
// en: Holy Sword Warrior
```

#### 複合主キーでの直接取得

```php
// 特定のユニットIDと言語でl10nレコードを取得
$l10n = MstUnitL10n::where('mst_unit_id', 1)
    ->where('language', 'ja')
    ->first();

echo $l10n->name; // "聖剣の戦士"
```

### l10nテーブル実装チェックリスト

新しいl10nテーブルを作成する際は、以下を確認してください：

**マイグレーション:**
- [ ] テーブル名は`{親テーブル名}__l10n`形式（アンダースコア2つ）
- [ ] `id`カラムは作成しない
- [ ] 複合PRIMARY KEY: `['親テーブル_id', 'language']`
- [ ] `language`カラムは`enum('language', config('language.supported'))`
- [ ] `deploy_key`カラムを追加（mstテーブルの場合）
- [ ] 親テーブルIDと言語コードにインデックスを設定

**Eloquentモデル:**
- [ ] `protected $primaryKey = null;`を設定
- [ ] `public $incrementing = false;`を設定
- [ ] `setKeysForSaveQuery()`メソッドをオーバーライド
- [ ] `$fillable`に`id`を含めない
- [ ] 親テーブルとのリレーション（`belongsTo`）を定義

**シーダー:**
- [ ] `truncate()`でべき等性を確保
- [ ] `create()`時に`id`を指定しない
- [ ] 親テーブルのIDと言語コードのみを指定
- [ ] `deploy_key`を全レコードに設定（mstテーブルの場合）

### 既存テーブルの複合主キー化

既に`id`カラムを持つl10nテーブルを複合主キー化する場合は、以下のマイグレーションを作成します：

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mst')->table('mst_unit__l10n', function (Blueprint $table) {
            // 1. 既存のPRIMARY KEYを削除
            $table->dropPrimary(['id']);
            
            // 2. idカラムを削除
            $table->dropColumn('id');
            
            // 3. 複合PRIMARY KEYを設定
            $table->primary(['mst_unit_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::connection('mst')->table('mst_unit__l10n', function (Blueprint $table) {
            // 複合PRIMARY KEYを削除
            $table->dropPrimary(['mst_unit_id', 'language']);
            
            // idカラムを再追加
            $table->id()->first();
        });
    }
};
```

**注意**: この変更を行う前に、必ず既存のシーダーとモデルを更新してください。

### 言語コードの管理

- 言語コードは `config/language.php` で一元管理
- マイグレーションでは `config('language.supported')` を参照してEnum型を定義
- サポート言語の追加・削除はconfigファイルを更新し、マイグレーションを作成

```php
// config/language.php
return [
    'supported' => [
        'ja',      // 日本語
        'en',      // 英語
        'zh-TW',   // 繁体字中国語
        'zh-CN',   // 簡体字中国語
        'hi',      // ヒンディー語
        'es',      // スペイン語
        'fr',      // フランス語
        'ar',      // アラビア語
        'id',      // インドネシア語
        'pt',      // ポルトガル語
        'bn',      // ベンガル語
        'ru',      // ロシア語
        'de',      // ドイツ語
        'ko',      // 韓国語
    ],
    'default' => 'ja',
];
```

## 課金プラットフォーム関連テーブル

### mst_billing_platform_product

AppStoreやGoogle Playなどの課金プラットフォームに登録された商品を管理するテーブル。

**用語の選定理由:**
- **"Product"**: Apple（"In-App Purchase Product"）とGoogle（"In-app product"）の両方で公式に使用されている用語
- **"SKU"**: ビジネス/EC分野で広く使われるが、ゲーム業界では"Product"の方が一般的
- **"Item"**: ゲーム内アイテムと混同される可能性があるため不適切

```php
Schema::connection('mst')->create('mst_billing_platform_product', function (Blueprint $table) {
    $table->id()->comment('商品ID');
    $table->string('platform_product_id', 255)->comment('プラットフォーム商品ID (com.example.gem100)');
    $table->enum('platform', ['AppStore', 'GooglePlay', 'PayPal', 'Stripe'])->comment('決済プラットフォーム');
    $table->enum('product_type', ['Consumable', 'NonConsumable', 'Subscription'])->comment('商品種別');
    $table->boolean('is_active')->default(true)->comment('有効フラグ');
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
    
    // 複合ユニークキー: 同じプラットフォームで同じplatform_product_idは登録できない
    $table->unique(['platform', 'platform_product_id']);
    $table->index('platform');
    $table->index('is_active');
    $table->index(['platform', 'is_active']);
});
```

**設計思想:**
- 商品の詳細情報（名前、価格、報酬内容）は別のマスターテーブル（`mst_in_app_purchase`）で管理
- このテーブルは**プラットフォーム側の商品IDとゲーム内商品の紐付け**のみを行う
- プラットフォームごとに異なる商品IDを持つ同一商品を、`mst_in_app_purchase`で統合管理

## 外部キー制約に関するポリシー

**重要: このプロジェクトでは外部キー制約（FOREIGN KEY）を使用しない。**

### 理由

1. **INSERT順序の自由度**
   - ゲームではテストデータやマスターデータを柔軟に投入する必要がある
   - 外部キー制約があると、親テーブル→子テーブルの順序を厳密に守る必要がある
   - データリセットやロールバック時に削除順序も制約される

2. **パフォーマンス**
   - 外部キー制約のチェックはINSERT/UPDATE/DELETE時にオーバーヘッドとなる
   - 高頻度のトランザクション処理では影響が大きい

3. **シャーディング対応**
   - 異なるデータベース間で外部キー制約は使用できない
   - `sys_player`（sysデータベース）と`trx_player`（trx1/trx2データベース）は異なるDBにある

4. **柔軟なデータ操作**
   - 開発・テスト環境でのデータ投入が容易
   - 業務システムのような厳密な整合性チェックは不要

### 代替手段

外部キー制約の代わりに、以下の方法でデータ整合性を保証する：

1. **アプリケーション層でのバリデーション**
   ```php
   // 例: アイテム付与時にマスターデータの存在確認
   $item = MstItem::find($itemId);
   if (!$item) {
       throw new InvalidItemException("Item not found: {$itemId}");
   }
   ```

2. **インデックスの適切な設定**
   ```php
   // 外部キー相当のカラムにインデックスを設定
   $table->index('sys_player_id');
   $table->index('mst_item_id');
   ```

3. **トランザクション処理**
   ```php
   DB::transaction(function () {
       // 関連データを一括で処理
       $player = SysPlayer::create([...]);
       TrxPlayer::create(['sys_player_id' => $player->id]);
   });
   ```

4. **定期的な整合性チェック（バッチ処理）**
   ```php
   // 孤立レコードの検出
   $orphanedItems = TrxItem::whereNotExists(function ($query) {
       $query->select('id')
           ->from('mst_item')
           ->whereColumn('mst_item.id', 'trx_item.mst_item_id');
   })->get();
   ```

### マイグレーションでの記述

**重要: マイグレーションファイルには絶対に外部キー制約を記述しないこと**

❌ **使用しない**:
```php
// 外部キー制約は定義しない
$table->foreign('sys_player_id')
    ->references('id')
    ->on('sys_player')
    ->onDelete('cascade');
```

✅ **推奨**:
```php
// インデックスのみ設定
$table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
$table->index('sys_player_id');
```

✅ **良い例**:
```php
Schema::connection('sys')->create('sys_friend_apply', function (Blueprint $table) {
    $table->bigIncrements('id')->comment('フレンドリクエストID');
    $table->unsignedBigInteger('request_sys_player_id')->comment('リクエスト送信者のプレイヤーID');
    $table->unsignedBigInteger('receive_sys_player_id')->comment('リクエスト受信者のプレイヤーID');
    $table->enum('status', ['Requested', 'Accepted', 'Deleted'])->default('Requested')->comment('ステータス');
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
    
    // インデックスのみ設定（外部キー制約は不要）
    $table->index('request_sys_player_id');
    $table->index('receive_sys_player_id');
    $table->index('status');
    $table->index(['receive_sys_player_id', 'status']);
});
```

### 注意事項

- **カラム名の命名規則は維持**: `{参照先テーブル名}_id`の形式を使用し、関連性を明示
- **コメントで関連を明記**: マイグレーションやモデルで参照先を明記する
- **Eloquentのリレーション**: 外部キー制約がなくても、Eloquentのリレーション機能は通常通り使用可能

```php
// モデルでのリレーション定義（外部キー制約不要）
public function sysPlayer(): BelongsTo
{
    return $this->belongsTo(SysPlayer::class, 'sys_player_id', 'id');
}
```

## Laravelデフォルトテーブル

**全てのデータベース**（adm, tol, sys, mst, log, trx1, trx2）には、以下のLaravelデフォルトテーブルが存在する：

- `cache` - Laravelのキャッシュストレージ
- `cache_locks` - キャッシュのロック管理
- `jobs` - Laravelのジョブキュー
- `job_batches` - バッチジョブ管理
- `failed_jobs` - 失敗したジョブの記録
- `migrations` - マイグレーション実行履歴

**注意**: `users`テーブルはデフォルトで作成しない。代わりに各プロジェクト固有のアカウントテーブル（例: `adm_account`）を使用する。

## マイグレーションファイル管理

### ディレクトリ構造

マイグレーションファイルは、データベースごとにディレクトリを分けて管理する。

```
database/migrations/
├── sys/          # sysデータベース用
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_01_12_000003_create_sys_deploy_master_table.php
│   ├── 2026_01_12_000007_create_sys_sharding_table.php
│   └── ...
├── trx/          # trx1, trx2データベース用（両方に適用）
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_01_12_000002_create_transaction_tables.php
│   └── ...
├── mst/          # mstデータベース用
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   └── ...
├── log/          # logデータベース用
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   └── ...
├── adm/          # admデータベース用
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   └── ...
└── tol/          # tolデータベース用（運営ツール）
    ├── 0001_01_01_000001_create_cache_table.php
    ├── 0001_01_01_000002_create_jobs_table.php
    ├── 2026_02_22_000001_create_tol_master_status_table.php
    ├── 2026_02_22_000003_create_tol_banner_table.php
    └── ...
```

### 複数接続対応マイグレーション

trxデータベースのように複数のシャードに同じテーブルを作成する場合、マイグレーション内でループ処理を行う。

#### パターン1: 複数接続を配列で定義

```php
return new class extends Migration
{
    /**
     * シャーディング対象の接続名
     */
    protected $connections = ['trx1', 'trx2'];

    public function up(): void
    {
        foreach ($this->connections as $connection) {
            Schema::connection($connection)->create('trx_player', function (Blueprint $table) {
                $table->bigInteger('sys_player_id')->unsigned()->primary();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->connections as $connection) {
            Schema::connection($connection)->dropIfExists('trx_player');
        }
    }
};
```

#### パターン2: 単一接続指定

sys、mst、tol などの単一データベース用マイグレーションでは、`Schema::connection()`で直接接続名を指定する。

**重要**: Laravelの`Migration`基底クラスとの競合を避けるため、`$connection`プロパティは使用しない。

```php
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 直接接続名を指定
        Schema::connection('tol')->create('tol_banner', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tol')->dropIfExists('tol_banner');
    }
};
```

### マイグレーション実行コマンド

#### apiプロジェクト

```bash
# sys データベース
docker exec api-php php artisan migrate --database=sys --path=database/migrations/sys

# mst データベース
docker exec api-php php artisan migrate --database=mst --path=database/migrations/mst

# log データベース
docker exec api-php php artisan migrate --database=log --path=database/migrations/log

# trx1 データベース
docker exec api-php php artisan migrate --database=trx1 --path=database/migrations/trx

# trx2 データベース
docker exec api-php php artisan migrate --database=trx2 --path=database/migrations/trx

# 注意: trxマイグレーションは同じファイルをtrx1とtrx2の両方に適用する
```

#### toolプロジェクト

```bash
# adm データベース
docker exec tool-php php artisan migrate --database=adm --path=database/migrations/adm

# tol データベース（運営ツール）
docker exec tool-php php artisan migrate --database=tol --path=database/migrations/tol
```

#### migrate:fresh を使う場合（全テーブル削除 + 再作成）

```bash
# 警告: 全データが削除されます
docker exec api-php php artisan migrate:fresh --database=sys --path=database/migrations/sys
docker exec tool-php php artisan migrate:fresh --database=tol --path=database/migrations/tol
```

### マイグレーションファイルのタイムスタンプ

- ファイル名のタイムスタンプは、テーブルの依存関係を考慮して設定する
- 外部キー制約は使用しないが、論理的な依存関係は考慮する
- 例: `sys_player`（2026_02_21_151847）→ `sys_sharding_node_player`（2026_02_21_152854）

## deploy_keyシステム - マスターデータのバージョン管理

### 概要

`deploy_key`は、マスターデータ（mstデータベースの全テーブル）のバージョン管理を行うためのシステムです。マスターデータの配信タイミングを制御し、「指定されたdeploy_key以下のレコードのみをSELECTする」という挙動を実現します。

### 適用対象

- **mstデータベースの全テーブル**に必須
- その他のデータベース（sys, trx, log, adm, tol）には適用しない

### カラム定義

```php
$table->integer('deploy_key')
    ->default(202601010)
    ->comment('デプロイキー（マスターデータバージョン管理）');
$table->index('deploy_key');
```

**仕様:**
- 型: `INT`
- デフォルト値: `202601010`
- NOT NULL
- インデックス必須
- コメント: 「デプロイキー（マスターデータバージョン管理）」で統一

**フォーマット:**
- `YYYYMMDDHH` 形式（例: 2026年1月1日午前1時 → `202601010`）
- 時刻は2桁（00〜23）

### マイグレーションでの実装

#### 新規テーブル作成時

```php
Schema::connection('mst')->create('mst_item', function (Blueprint $table) {
    $table->id()->comment('アイテムID');
    $table->string('category', 50)->comment('アイテムカテゴリ');
    $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
    $table->boolean('is_active')->default(true)->comment('有効フラグ');
    
    // deploy_key追加（必須）
    $table->integer('deploy_key')
        ->default(202601010)
        ->comment('デプロイキー（マスターデータバージョン管理）');
    
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
    $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
    
    $table->index('category');
    $table->index('is_active');
    $table->index('sort_desc');
    $table->index('deploy_key');  // インデックス必須
});
```

#### 既存テーブルへの追加

複数のmstテーブルに一括でdeploy_keyを追加する場合は、1つのマイグレーションファイルにまとめる。

```php
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'mst_billing_platform_product',
            'mst_in_app_purchase',
            'mst_in_app_purchase__l10n',
            'mst_in_app_purchase_content',
            'mst_in_app_purchase_effect',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::connection('mst')->hasColumn($tableName, 'deploy_key')) {
                Schema::connection('mst')->table($tableName, function (Blueprint $table) {
                    $table->integer('deploy_key')
                        ->default(202601010)
                        ->after('id')  // 配置位置を指定（通常はidの直後）
                        ->comment('デプロイキー（マスターデータバージョン管理）');
                    $table->index('deploy_key');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'mst_billing_platform_product',
            'mst_in_app_purchase',
            'mst_in_app_purchase__l10n',
            'mst_in_app_purchase_content',
            'mst_in_app_purchase_effect',
        ];

        foreach ($tables as $tableName) {
            Schema::connection('mst')->table($tableName, function (Blueprint $table) {
                $table->dropIndex(['deploy_key']);
                $table->dropColumn('deploy_key');
            });
        }
    }
};
```

### Eloquentモデルでの実装

#### 基底モデル: _BaseMst

全てのmstモデルが継承する基底クラスに`deploy_key`を追加。

```php
namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Model;

abstract class _BaseMst extends Model
{
    /**
     * 接続するデータベース
     */
    protected $connection = 'mst';

    /**
     * マスフィラブル属性
     */
    protected $fillable = [
        'deploy_key',
    ];

    /**
     * キャストする属性
     */
    protected $casts = [
        'deploy_key' => 'integer',
    ];
}
```

#### 各モデルクラス

各mstモデルでは、`$fillable`に明示的に`deploy_key`を追加する（基底クラスの継承に加えて）。

```php
namespace App\Models\Mst;

class MstInAppPurchase extends _BaseMst
{
    protected $table = 'mst_in_app_purchase';

    protected $fillable = [
        'deploy_key',  // 明示的に追加
        'type',
        'paid_diamond_amount',
        'effect_duration_days',
        'purchase_limit',
        'purchase_limit_reset',
        'app_store_product_id',
        'google_play_product_id',
        'sort_desc',
        'is_active',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'paid_diamond_amount' => 'integer',
        'effect_duration_days' => 'integer',
        'purchase_limit' => 'integer',
        'is_active' => 'boolean',
    ];
}
```

**重要**: 複合主キーを持つモデル（例: `MstInAppPurchaseL10n`）でも同様に`deploy_key`を追加する。

```php
class MstInAppPurchaseL10n extends _BaseMst
{
    protected $table = 'mst_in_app_purchase__l10n';
    protected $primaryKey = null;  // 複合主キーの場合
    public $incrementing = false;

    protected $fillable = [
        'deploy_key',  // 複合主キーモデルでも必須
        'mst_in_app_purchase_id',
        'language',
        'name',
        'description',
    ];
}
```

### シーダーでの実装

全てのmstシーダーで、レコード作成時に`deploy_key`を明示的に指定する。

```php
class MstInAppPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        // メインテーブル
        $diamond100 = MstInAppPurchase::create([
            'deploy_key' => 202601010,  // 必須
            'type' => 'Diamond',
            'paid_diamond_amount' => 100,
            'is_active' => true,
        ]);

        // 多言語テーブル
        MstInAppPurchaseL10n::create([
            'deploy_key' => 202601010,  // 必須
            'mst_in_app_purchase_id' => $diamond100->id,
            'language' => 'ja',
            'name' => '有償ダイアモンド100個',
        ]);

        // 複合主キーテーブル
        MstInAppPurchaseContent::create([
            'deploy_key' => 202601010,  // 必須
            'mst_in_app_purchase_id' => $pack->id,
            'content_type' => 'FreeDiamond',
            'amount' => 500,
        ]);
    }
}
```

**チェックリスト:**
- [ ] 全てのメインテーブルレコードに`deploy_key`を設定
- [ ] 全ての多言語テーブル（__l10n）レコードに`deploy_key`を設定
- [ ] 全ての関連テーブル（content, effect等）レコードに`deploy_key`を設定
- [ ] 値は統一（例: 202601010）

### 使用例

#### クエリでのフィルタリング

```php
// 指定されたdeploy_key以下のレコードを取得
$deployKey = 202602010;
$products = MstInAppPurchase::where('deploy_key', '<=', $deployKey)
    ->where('is_active', true)
    ->orderBy('sort_desc', 'desc')
    ->get();
```

#### グローバルスコープの実装（将来的に）

```php
// _BaseMstモデルにグローバルスコープを追加
protected static function booted()
{
    static::addGlobalScope('deploy_key', function (Builder $builder) {
        $currentDeployKey = config('app.current_deploy_key', 202601010);
        $builder->where('deploy_key', '<=', $currentDeployKey);
    });
}
```

### バージョン管理の運用例

**シナリオ**: 2026年2月1日午前0時に新しい課金商品をリリース

1. **新商品データ作成**
   ```php
   MstInAppPurchase::create([
       'deploy_key' => 202602010,  // 2026年2月1日午前0時
       'type' => 'Pack',
       'name' => 'New Year Pack',
   ]);
   ```

2. **クライアント側の対応**
   ```php
   // 古いクライアント: deploy_key=202601010
   // → 新商品（202602010）は取得されない
   
   // 新しいクライアント: deploy_key=202602010
   // → 旧商品 + 新商品の両方が取得される
   ```

3. **段階的ロールアウト**
   - データベースには先に登録（deploy_key=202602010）
   - アプリ更新のタイミングで、クライアントが新しいdeploy_keyを使用
   - サーバー側でdeploy_keyを動的に変更することで、即座に新商品を表示可能

### 注意事項

1. **デフォルト値の統一**: 初期値は`202601010`で統一
2. **インデックス必須**: パフォーマンスのため必ずインデックスを作成
3. **全mstテーブルに適用**: 例外なく全てのmstテーブルに追加
4. **シーダーでの明示**: `create()`時に必ず`deploy_key`を指定
5. **複合主キーテーブルも対象**: L10nテーブルなども必ず含める

### 重複インデックスの回避

マイグレーションファイル作成時の注意事項：

1. **unique()制約は自動的にインデックスを作成する**
   ```php
   // ❌ 悪い例（重複インデックス）
   $table->string('uuid')->unique();
   $table->index('uuid');  // 重複
   
   // ✅ 良い例
   $table->string('uuid')->unique();  // これだけでOK
   ```

2. **PRIMARY KEYも自動的にインデックスを作成する**
   ```php
   // ❌ 悪い例
   $table->bigInteger('sys_player_id')->primary();
   $table->index('sys_player_id');  // 重複
   
   // ✅ 良い例
   $table->bigInteger('sys_player_id')->primary();  // これだけでOK
   ```

3. **既存マイグレーションの修正より新規マイグレーション**
   - 重複インデックスを削除する場合は、新しいマイグレーションファイルを作成
   - ただし、最初から重複を作らないようにすれば不要

## 日付カラムの型指定

**重要: 全ての日付・日時カラムは `datetime` 型を使用する。`timestamp` 型は使用しない。**

### 理由

1. **2038年問題の回避**
   - `timestamp`: 2038年1月19日までしか扱えない（4バイトUNIX時間の限界）
   - `datetime`: 9999年12月31日まで扱える

2. **グローバル展開への対応**
   - `timestamp`: MySQLがタイムゾーン変換を自動実行（予期しない挙動の原因）
   - `datetime`: タイムゾーン非依存で保存した値がそのまま返される
   - アプリケーション層でUTC統一管理が容易

3. **長期運営ゲームへの対応**
   - モバイルゲームは10年以上運営されることがある
   - 2038年問題は現実的なリスク

4. **開発・デバッグの容易さ**
   - 全環境で同じ値が表示される
   - ログ解析・データ調査が容易

### マイグレーションでの記述

#### ❌ 使用しない

```php
// timestamp型は使用しない
$table->timestamp('created_at')->nullable();
$table->timestamp('updated_at')->nullable();
$table->timestamp('last_login_at')->nullable();
$table->timestamps();  // 内部的にtimestamp型を使用

// datetimeとtimestampを混在させない
$table->dateTime('start_at');
$table->timestamp('end_at');  // NG
```

#### ✅ 推奨

```php
// datetime型を使用
$table->dateTime('created_at')->nullable()->comment('作成日時');
$table->dateTime('updated_at')->nullable()->comment('更新日時');
$table->dateTime('last_login_at')->nullable()->comment('最終ログイン日時');
$table->dateTime('start_at')->nullable()->comment('開始日時');
$table->dateTime('end_at')->nullable()->comment('終了日時');
$table->dateTime('expires_at')->comment('有効期限');
$table->dateTime('revoked_at')->nullable()->comment('無効化日時');
$table->dateTime('failed_at')->useCurrent()->comment('失敗日時');
```

#### timestamps()メソッドの置き換え

Laravelの`timestamps()`メソッドは内部的に`timestamp`型を使用するため、明示的に`created_at`と`updated_at`を定義する。

```php
// ❌ 使用しない
$table->timestamps();

// ✅ 推奨
$table->dateTime('created_at')->nullable()->comment('作成日時');
$table->dateTime('updated_at')->nullable()->comment('更新日時');
```

### Eloquentモデルでの設定

Laravelはデフォルトで`created_at`と`updated_at`を`timestamp`型として扱うが、マイグレーションで`datetime`型を使用する場合は特に設定変更不要。Laravelが自動的に適切にキャストする。

```php
class MstItem extends Model
{
    // 特別な設定は不要
    // LaravelがDBの型に合わせて自動的にキャストする
    
    protected $casts = [
        // 必要に応じて明示的にキャスト
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];
}
```

### アプリケーション層でのUTC管理

```php
// config/app.php
'timezone' => 'UTC',  // アプリケーションタイムゾーンをUTCに設定

// データベース保存時は常にUTC
$player->last_login_at = now();  // UTC時刻で保存

// 表示時にユーザーのタイムゾーンに変換
$lastLogin = $player->last_login_at->timezone('Asia/Tokyo');
```

### 既存のtimestamp型カラムの移行

既にtimestamp型で作成されているカラムがある場合は、マイグレーションで変更する。

```php
public function up(): void
{
    Schema::connection('sys')->table('sys_player', function (Blueprint $table) {
        $table->dateTime('created_at')->nullable()->change();
        $table->dateTime('updated_at')->nullable()->change();
        $table->dateTime('last_login_at')->nullable()->change();
    });
}

public function down(): void
{
    Schema::connection('sys')->table('sys_player', function (Blueprint $table) {
        $table->timestamp('created_at')->nullable()->change();
        $table->timestamp('updated_at')->nullable()->change();
        $table->timestamp('last_login_at')->nullable()->change();
    });
}
```

### 統一ルールまとめ

| 項目 | ルール |
|------|--------|
| 日付カラムの型 | 全て`datetime`型を使用 |
| `timestamp`型 | **使用禁止** |
| `timestamps()`メソッド | **使用禁止**（明示的に`created_at`/`updated_at`を定義） |
| アプリケーションTZ | UTC（`config/app.php`） |
| データベース保存 | UTC時刻で統一 |
| 表示時の変換 | Carbon/Laravelの機能でユーザーTZに変換 |
| コメント | 全ての日付カラムに「○○日時」のコメントを付ける |
| nullable | 通常は`nullable()`、NOT NULL制約が必要な場合のみ省略 |

## ログテーブル（logデータベース）の設計ルール

### 概要

logデータベースには、ゲーム内のアクションを記録するinsert onlyのログテーブルを配置します。これらのテーブルは分析や監査のために使用され、一度挿入されたレコードは更新・削除されません。

### 基本設計方針

1. **Insert Only**
   - レコードは挿入のみ、更新・削除は行わない
   - `updated_at`カラムは存在しない

2. **専用データベース接続**
   - ログ専用の`log`接続を使用
   - トランザクションテーブルと物理的に分離

3. **日時カラムの二重管理**
   - `system_at`: アプリケーション側で設定（デバッグ時の日時変更に連動）
   - `created_at`: MySQL `CURRENT_TIMESTAMP`で自動設定（実際の記録時刻）

### テーブル構造の標準パターン

```php
Schema::connection('log')->create('log_equipment', function (Blueprint $table) {
    $table->id()->comment('ログID');
    
    // ビジネスカラム
    $table->string('unique_request_id', 36)->index()->comment('リクエスト一意識別子');
    $table->unsignedBigInteger('sys_player_id')->index()->comment('sys_playerテーブルのID');
    $table->unsignedBigInteger('trx_equipment_id')->comment('trx_equipmentテーブルのID');
    $table->unsignedInteger('mst_equipment_id')->comment('mst_equipmentテーブルのID');
    $table->unsignedTinyInteger('before_grade')->comment('変更前グレード');
    $table->unsignedTinyInteger('after_grade')->comment('変更後グレード');
    $table->unsignedSmallInteger('before_level')->comment('変更前レベル');
    $table->unsignedInteger('before_level_exp')->comment('変更前経験値');
    $table->unsignedSmallInteger('after_level')->comment('変更後レベル');
    $table->unsignedInteger('after_level_exp')->comment('変更後経験値');
    
    // 日時カラム（二重管理）
    $table->dateTime('system_at')->index()->comment('システム日時（デバッグ時の日時変更に連動）');
    $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('記録日時（実際のタイムスタンプ）');
    
    // updated_atカラムは作成しない
});
```

### 日時カラムの設計詳細

#### system_at

- **用途**: ビジネスロジック用のタイムスタンプ
- **設定方法**: アプリケーション側で明示的に設定
- **特徴**: デバッグ機能で日時を変更した場合、この値も変更される
- **インデックス**: 必須（日付範囲検索で使用）

```php
// アプリケーション側での設定例
LogEquipment::create([
    'sys_player_id' => $playerId,
    'trx_equipment_id' => $equipmentId,
    'system_at' => now(),  // デバッグ時の日時変更に連動
    // ...
]);
```

#### created_at

- **用途**: 実際の記録時刻（監査・分析用）
- **設定方法**: MySQL側で`CURRENT_TIMESTAMP`により自動設定
- **特徴**: デバッグ機能の日時変更に影響されない
- **インデックス**: 不要（主にsystem_atで検索）

```php
// マイグレーションでの定義
$table->dateTime('created_at')
    ->default(DB::raw('CURRENT_TIMESTAMP'))
    ->comment('記録日時（実際のタイムスタンプ）');
```

#### updated_atカラムは存在しない

ログテーブルはinsert onlyのため、`updated_at`カラムは作成しません。

### Eloquentモデルの実装

#### 基底クラス: _BaseLog

```php
<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;
use App\Models\Log\Interfaces\_BaseLogInterface;

abstract class _BaseLog extends Model implements _BaseLogInterface
{
    /**
     * ログデータベース接続を使用
     */
    protected $connection = 'log';

    /**
     * updated_atを無効化（ログは更新されない）
     */
    const UPDATED_AT = null;
}
```

**重要な設定**:
- `$connection = 'log'`: ログデータベース接続を使用
- `const UPDATED_AT = null`: updated_atカラムを無効化

#### 各ログモデルの実装

```php
<?php

namespace App\Models\Log;

use App\Models\Log\Interfaces\_BaseLogInterface;

class LogEquipment extends _BaseLog implements _BaseLogInterface
{
    protected $connection = 'log';
    protected $table = 'log_equipment';

    /**
     * ✅ ビジネスカラムとsystem_atのみ含める
     * ❌ created_at, updated_atは含めない
     */
    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'trx_equipment_id',
        'mst_equipment_id',
        'before_grade',
        'after_grade',
        'before_level',
        'before_level_exp',
        'after_level',
        'after_level_exp',
        'system_at',  // アプリケーション側で設定
    ];

    /**
     * ✅ ビジネスカラムとsystem_atのみキャスト
     * ❌ created_at, updated_atは含めない
     */
    protected $casts = [
        'sys_player_id' => 'integer',
        'trx_equipment_id' => 'integer',
        'mst_equipment_id' => 'integer',
        'before_grade' => 'integer',
        'after_grade' => 'integer',
        'before_level' => 'integer',
        'before_level_exp' => 'integer',
        'after_level' => 'integer',
        'after_level_exp' => 'integer',
        'system_at' => 'datetime',  // デバッグ時の日時変更に連動
    ];

    // created_at, updated_atは$fillableや$castsに含めない
    // Laravelが自動的に処理する
}
```

### 実装ルールまとめ

#### マイグレーション

- [ ] `log`接続を使用: `Schema::connection('log')`
- [ ] `system_at`カラムを追加（`dateTime`型、インデックス必須）
- [ ] `created_at`カラムを追加（`dateTime`型、`useCurrent()`でデフォルト値設定）
- [ ] `updated_at`カラムは作成しない
- [ ] `timestamps()`メソッドは使用しない

#### Eloquentモデル

- [ ] `_BaseLog`を継承
- [ ] `protected $connection = 'log';`を設定
- [ ] `$fillable`にビジネスカラムと`system_at`のみ含める
- [ ] `$casts`にビジネスカラムと`system_at`のみ含める
- [ ] `$fillable`に`created_at`, `updated_at`を含めない
- [ ] `$casts`に`created_at`, `updated_at`を含めない

#### 理由

**created_atとupdated_atを$fillableや$castsに含めない理由:**

1. **Laravelの自動管理機能**
   - Laravelは`created_at`と`updated_at`を特別扱いし、自動的に管理する
   - `$fillable`や`$casts`に含めなくても、Laravelが適切にキャストする

2. **_BaseLogの設定との整合性**
   - `const UPDATED_AT = null`により、`updated_at`は無効化されている
   - `created_at`はLaravelが自動的に処理（MySQL側のデフォルト値と連動）

3. **明示的な分離**
   - ビジネスカラム（アプリケーション側で設定）と、フレームワーク管理カラム（Laravel自動処理）を分離
   - `system_at`はビジネスロジック用のため、明示的に`$fillable`と`$casts`に含める

### 既存のログテーブル一覧

このプロジェクトには以下のログテーブルが存在します:

| テーブル名 | 用途 |
|-----------|------|
| `log_access` | APIアクセスログ |
| `log_player` | プレイヤーレベル変更ログ |
| `log_item` | アイテム増減ログ |
| `log_gacha` | ガチャ実行ログ |
| `log_unit` | ユニット取得・強化ログ |
| `log_equipment` | 装備取得・強化ログ |
| `log_in_app_purchase` | 課金ログ |

全てのログテーブルは上記のルールに従って実装されています。

### 使用例

#### ログの記録

```php
// 装備強化ログの記録
LogEquipment::create([
    'unique_request_id' => $requestId,
    'sys_player_id' => $playerId,
    'trx_equipment_id' => $equipment->id,
    'mst_equipment_id' => $equipment->mst_equipment_id,
    'before_grade' => $beforeGrade,
    'after_grade' => $afterGrade,
    'before_level' => $beforeLevel,
    'before_level_exp' => $beforeLevelExp,
    'after_level' => $afterLevel,
    'after_level_exp' => $afterLevelExp,
    'system_at' => now(),  // デバッグ時の日時変更に連動
]);
// created_atはMySQLが自動設定（実際の記録時刻）
```

#### ログの検索

```php
// 特定プレイヤーの装備強化履歴を取得
$logs = LogEquipment::where('sys_player_id', $playerId)
    ->whereBetween('system_at', [$startDate, $endDate])  // ビジネスロジック用の日時で検索
    ->orderBy('system_at', 'desc')
    ->get();

// デバッグ時の日時と実際の記録時刻を比較
foreach ($logs as $log) {
    echo "ビジネス日時: {$log->system_at}\n";    // デバッグ時の日時変更に連動
    echo "実際の記録時刻: {$log->created_at}\n"; // 実際のタイムスタンプ
}
```

### デバッグ機能との連動

ゲーム開発では、テスト目的で日時を変更する機能が必要になることがあります。

**シナリオ**: 時限イベントをテストするため、システム日時を1週間後に設定

```php
// デバッグ設定: システム日時を1週間後に
$debugTime = now()->addWeek();

// ログ記録時
LogEquipment::create([
    'sys_player_id' => $playerId,
    'system_at' => $debugTime,  // 1週間後の日時（デバッグ設定）
    // ...
]);

// データベースに記録される:
// system_at: 2026-03-02 10:00:00（デバッグで設定した1週間後）
// created_at: 2026-02-23 10:00:00（実際の現在時刻、MySQL自動設定）
```

この二重管理により:
- ビジネスロジックはデバッグ日時で動作
- 実際の記録時刻も保持されるため、デバッグログと本番ログの区別が可能
- 分析時にどちらの日時も利用可能

---

 ## ENUM型の使用ルール

### 概要

ENUM型は適切に使用しないと、サービス全体の停止を引き起こす可能性があります。テーブルの種類に応じて、ENUM型の使用可否を明確に区別する必要があります。

### 使用可否の基準

**重要:** ENUM型の拡張には、テーブルの再構築が必要となり、ゲーム全体を停止させる必要があります。

#### ENUM型を使用してよいテーブル

✅ **mst_*** (マスターテーブル)
- デプロイ時に一括更新されるため、ENUM拡張も同時に実施可能
- 計画的なメンテナンス時に更新できる

✅ **tol_*** (運営ツールテーブル)
- 運営ツール専用DBのため、ゲームサービスを停止せずにALTERを実施可能

✅ **adm_*** (管理テーブル)
- 管理者向けDBのため、ゲームサービスを停止せずにALTERを実施可能

#### ENUM型を使用してはいけないテーブル

❌ **trx_*** (トランザクションテーブル)
- API経由で常時書き込まれるため、テーブルロックが許容できない
- **STRING型を使用すること**

❌ **sys_*** (システムテーブル)
- API経由で常時書き込まれるため、テーブルロックが許容できない
- **STRING型を使用すること**

❌ **log_*** (ログテーブル)
- API経由で常時書き込まれるため、テーブルロックが許容できない
- **STRING型を使用すること**

### ENUM値・定数のケース統一ルール

**重要: DBに登録される全ての文字列値はスネークケースに統一する。**

#### 理由

- `mst_billing_platform_product.platform_product_id` のようにApp Storeの商品IDはスネークケースで登録される
- ENUMやその他の定数がパスカルケースだと「ここだけスネーク、他はパスカル」となり一貫性がなくなる
- スネークケースに統一することで、DBに保存される全文字列の表記が一致する

#### ルール

```php
// ✅ Good: ENUM値はスネークケース
$table->enum('billing_platform', ['app_store', 'google_play', 'pay_pal', 'stripe']);
$table->enum('type', ['attack', 'defense', 'support']);
$table->enum('status', ['applied', 'accepted', 'rejected', 'deleted']);

// ✅ Good: PHP定数・Enumクラスの値もスネークケース
const PLATFORM_APP_STORE = 'app_store';
case APP_STORE = 'app_store';

// ❌ Bad: パスカルケース
$table->enum('billing_platform', ['AppStore', 'GooglePlay']);
const PLATFORM_APP_STORE = 'AppStore';
case APP_STORE = 'AppStore';
```

#### 例外（スネークケースにしない値）

以下は外部仕様・規格に従うため変更しない：
- `language` カラムの値: `ja`, `en`, `zh-TW` 等（RFC 5646準拠）
- `rarity` カラムの値: `UR`, `SSR`, `SR`, `R`, `UC`, `C`（ゲーム業界の慣習的な略称）
- `operation_type`: `insert`, `update`, `delete`（SQL操作名）
- `PITR`の`operation`: `INSERT`, `UPDATE`, `DELETE`（SQL操作名・大文字）

### 実装例

#### ❌ Bad: trx/sys/logテーブルでENUMを使用

```php
// マイグレーション
$table->enum('billing_platform', ['app_store', 'google_play']);

// 問題: 新しい値を追加する際にサービス停止が必要
```

#### ✅ Good: trx/sys/logテーブルではSTRINGを使用

```php
// マイグレーション
$table->string('billing_platform')
    ->comment('課金プラットフォーム (app_store, google_play等)');

// 利点: 新しい値を追加してもテーブル構造の変更不要
```

#### ✅ Good: mst/tol/admテーブルではENUMを使用可能（値はスネークケース）

```php
// マイグレーション
$table->enum('status', ['active', 'inactive', 'maintenance']);
$table->enum('billing_platform', ['app_store', 'google_play', 'pay_pal', 'stripe']);

// 理由: デプロイ時に計画的に更新可能
```

### ENUM型の拡張が引き起こす問題

**理由:**
1. ENUM型に新しい値を追加するには、`ALTER TABLE ... MODIFY COLUMN`が必要
2. この操作はテーブル全体のロックが発生し、サービスを停止させる必要がある
3. マスターテーブルはデプロイ時に計画的に更新できるが、トランザクションテーブルは随時更新されるため不可能

**技術的な詳細:**
```sql
-- ENUM拡張の例（テーブル全体がロックされる）
ALTER TABLE trx_diamond_balance 
MODIFY COLUMN billing_platform ENUM('app_store', 'google_play', 'pay_pal', 'stripe', 'new_platform');

-- この間、テーブルへの書き込みが全てブロックされる
-- 大量のレコードがある場合、数分〜数十分かかる可能性
```

### ベストプラクティス

1. **設計時の判断基準**
   - mst/tol/admテーブル: ENUM型を使用可（値はスネークケース）
   - sys/trx/logテーブル: STRING型を使用（値はスネークケース）

2. **コメントでの補足**
   - STRING型を使用する場合、許容される値をコメントで明記
   - 例: `->comment('課金プラットフォーム (app_store, google_play等)')`

3. **バリデーション**
   - STRING型でも、アプリケーション層で値の検証を行う
   - Enumクラスやバリデーションルールで許容値を定義（値はスネークケース）

```php
// ✅ Good: Enumクラスの値はスネークケース
enum BillingPlatform: string
{
    case AppStore   = 'app_store';
    case GooglePlay = 'google_play';
    case PayPal     = 'pay_pal';
    case Stripe     = 'stripe';
}

// バリデーション
$request->validate([
    'billing_platform' => ['required', new Enum(BillingPlatform::class)],
]);
```

---

## 課金通貨管理システム（ダイヤモンド管理）

### 設計方針


課金通貨（ダイヤモンドなど）は、**現在値テーブル + 残高テーブル**の2テーブル構成で管理します。

#### この設計を採用する理由

1. **先入先出（FIFO）方式での消費管理**
   - 会計上の原則に従った消費順序の管理
   - 古い購入から順番に消費することで、返金時の公平性を保証

2. **返金時の正確な金額計算**
   - 購入時の単価を保持することで、正確な返金額を算出可能
   - プラットフォームごとに異なる手数料に対応

3. **課金プラットフォーム別管理**
   - 複数の決済方法をサポート（AppStore, GooglePlay, PayPal, Stripe等）
   - プラットフォームストアの規約遵守

4. **有償/無償の分離管理**
   - 有償ダイヤモンドと無償ダイヤモンドを明確に分離
   - 消費優先順位の制御（無償→有償の順で消費）

5. **監査対応**
   - 購入履歴の完全な追跡
   - レコードは削除せず、残高を0にすることで履歴を保持

### プラットフォームの定義

このプロジェクトでは2種類のプラットフォームを管理します：

#### platform（プラットフォーム）

**定義:** ユーザーのデバイス環境

**値の例:**
- `Apple`
- `Google`

**用途:**
- trx_diamondの主キーの一部として使用
- デバイスごとにダイヤモンドを分離管理

#### billing_platform（決済プラットフォーム）

**定義:** 実際に決済を行う場所

**値の例:**
- `AppStore`
- `GooglePlay`
- `PayPal`
- `Stripe`

**用途:**
- マスターテーブル（`mst_billing_platform_product`）で商品定義
- トランザクションテーブル（`trx_diamond_balance`）で残高管理

### プラットフォームの組み合わせ例

| platform | billing_platform | 説明 |
|----------|------------------|------|
| Apple | AppStore | iOS アプリ内課金 |
| Google | GooglePlay | Android アプリ内課金 |
| Apple | PayPal | AppleデバイスからWebブラウザ経由でPayPal決済 |
| Google | PayPal | AndroidデバイスからWebブラウザ経由でPayPal決済 |
| Apple | Stripe | AppleデバイスからWebブラウザ経由でStripe決済 |
| Google | Stripe | AndroidデバイスからWebブラウザ経由でStripe決済 |

**注意:** マスターテーブル（`mst_billing_platform_product`）では、`billing_platform`カラムで決済プラットフォームを管理（`enum('AppStore', 'GooglePlay', 'PayPal', 'Stripe')`）。将来的に新しい決済プラットフォームを追加する場合は、ENUM拡張ではなくマスターデータの更新で対応します。

### テーブル構成

#### 1. 現在値テーブル（trx_diamond）

**PRIMARY KEY:** `(sys_player_id, platform)`

```sql
CREATE TABLE trx_diamond (
    sys_player_id BIGINT UNSIGNED NOT NULL COMMENT 'sys_playerテーブルのID',
    platform VARCHAR(191) NOT NULL COMMENT 'プラットフォーム (Apple, Google)',
    paid_amount INT UNSIGNED DEFAULT 0 COMMENT '有償ダイヤモンド数',
    free_amount INT UNSIGNED DEFAULT 0 COMMENT '無償ダイヤモンド数',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sys_player_id, platform)
);
```

**役割:** プレイヤーの現在のダイヤモンド保有数を高速参照

**特徴:**
- 複合主キーで高速アクセス
- プラットフォームごとに保有数を分離
- 有償/無償を別カラムで管理

#### 2. 残高テーブル（trx_diamond_balance）

**PRIMARY KEY:** `id`

```sql
CREATE TABLE trx_diamond_balance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sys_player_id BIGINT UNSIGNED NOT NULL COMMENT 'sys_playerテーブルのID',
    platform VARCHAR(191) NOT NULL COMMENT 'プラットフォーム (Apple, Google)',
    billing_platform VARCHAR(191) NOT NULL COMMENT '決済プラットフォーム (AppStore, GooglePlay, PayPal, Stripe等)',
    current_amount INT UNSIGNED NOT NULL COMMENT '現在の残高',
    purchase_amount INT UNSIGNED NOT NULL COMMENT '購入時の数量',
    unit_price DECIMAL(10,2) NOT NULL COMMENT '単価（返金計算用）',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (sys_player_id, platform, billing_platform)
);
```

**役割:** 購入単位で残高を管理し、FIFO消費（IDソート）と返金計算を実現

**特徴:**
- `id`の昇順 = 購入順（auto incrementでFIFO保証）
- 購入時の単価を保持（返金計算用）
- 決済プラットフォームごとに分離
- レコードは削除せず、`current_amount`を0にして履歴保持

**重要:** トランザクションテーブルでは`billing_platform`をSTRING型で定義し、ENUM拡張によるサービス停止を回避します。

### 実装パターン

#### 購入時の処理

```php
// 1. trx_diamondを更新（現在値を加算）
$diamond = $trxDiamondRepo->findByPlayer($playerId, 'Apple');
$diamond->paid_amount += 100;
$trxDiamondRepo->save($diamond);

// 2. trx_diamond_balanceに購入レコードを追加
$balance = new TrxDiamondBalance([
    'sys_player_id' => $playerId,
    'platform' => 'Apple',
    'billing_platform' => 'AppStore',
    'current_amount' => 100,      // 現在の残高
    'purchase_amount' => 100,     // 購入時の数量
    'unit_price' => 1.20,         // 単価 (例: ¥120/100個)
]);
$trxDiamondBalanceRepo->save($balance);
```

#### 消費時の処理（FIFO方式）

```php
// 1. 古い順に取得（idでソート - auto incrementによりFIFO保証）
$balances = TrxDiamondBalance::where('sys_player_id', $playerId)
    ->where('platform', 'Apple')
    ->where('billing_platform', 'AppStore')
    ->where('current_amount', '>', 0)
    ->orderBy('id', 'asc')  // FIFO: IDの昇順 = 購入順
    ->get();

// 2. 順番に消費
$remainingToConsume = 50;  // 消費したい数量
foreach ($balances as $balance) {
    if ($remainingToConsume <= 0) break;
    
    $consumed = min($balance->current_amount, $remainingToConsume);
    $balance->current_amount -= $consumed;
    $remainingToConsume -= $consumed;
    
    $trxDiamondBalanceRepo->save($balance);
}

// 3. trx_diamondを更新（現在値を減算）
$diamond->paid_amount -= 50;
$trxDiamondRepo->save($diamond);
```

#### 返金計算

```php
// 残高から返金額を計算
$refundAmount = 0;
foreach ($balances as $balance) {
    if ($balance->current_amount > 0) {
        $refundAmount += $balance->current_amount * $balance->unit_price;
    }
}
// 例: 30個残 × ¥1.20 = ¥36
```

### 重要なルール

#### ✅ 実装すべき

1. **有償/無償の分離管理**
   - `paid_amount`と`free_amount`を別カラムで管理
   - 消費時は無償→有償の順で消費

2. **プラットフォーム別管理**
   - `platform`: デバイス環境（Apple, Google）
   - trx_diamondの主キーの一部として使用

3. **決済プラットフォーム別管理**
   - `billing_platform`: 決済システム（AppStore, GooglePlay, PayPal, Stripe等）
   - trx_diamond_balanceで管理

4. **FIFO方式での消費**
   - `id`の昇順でソート
   - auto incrementにより購入順序を保証

5. **購入時の単価を保持**
   - `unit_price`カラムで保存
   - 返金計算に使用

6. **現在値テーブルと残高テーブルの同期**
   - 購入・消費時に両方のテーブルを更新
   - トランザクション内で整合性を保証

#### ❌ 実装してはいけない

1. **残高テーブルのレコード削除**
   - 監査用に履歴を保持
   - `current_amount`を0にして履歴保持

2. **LIFO（後入先出）方式**
   - 会計原則に反する
   - 返金時の公平性が失われる

3. **プラットフォーム横断での消費**
   - Appleプラットフォームで購入したダイヤをGoogleで消費することは不可
   - プラットフォームストアの規約違反

4. **有償/無償の混在**
   - 明確に分離して管理
   - 消費優先順位を厳守

### プラットフォーム管理の詳細

#### platformとbilling_platformの違い

- **platform**: ユーザーのデバイス環境（Apple, Google）
  - trx_diamondの主キーの一部
  - デバイスごとにダイヤモンドを分離

- **billing_platform**: 決済システム（AppStore, GooglePlay, PayPal, Stripe等）
  - trx_diamond_balanceで管理
  - 同じプレイヤー、同じplatformでも決済方法ごとに別レコード

**例:**
```php
// Appleデバイス + AppStore決済
['sys_player_id' => 1, 'platform' => 'Apple', 'billing_platform' => 'AppStore']

// Appleデバイス + PayPal決済（別レコード）
['sys_player_id' => 1, 'platform' => 'Apple', 'billing_platform' => 'PayPal']
```

#### プラットフォーム分離の理由

1. **AppStore/GooglePlayの返金処理が別々**
   - それぞれのプラットフォームで独立した返金処理
   - 手数料率も異なる

2. **プラットフォームストアの規約遵守**
   - アプリ内課金とWeb課金の分離が必須
   - クロスプラットフォーム購入の禁止

3. **返金時の正確な金額計算**
   - 決済プラットフォームごとに手数料が異なる
   - 購入単位での返金額計算が必要

4. **法的リスクの低減**
   - 各プラットフォームの利用規約に準拠
   - 不正な課金回避の防止

#### FIFO方式の理由

1. **会計上の原則**
   - 先入先出法は会計の標準手法
   - 監査対応が容易

2. **返金時の公平性**
   - 古い購入から返金することで、ユーザーに有利な計算
   - 透明性の確保

3. **法的リスクの低減**
   - 消費者保護の観点から推奨される方式
   - トラブル時の説明が容易

### データ型の選択

#### trx_diamond（現在値テーブル）

- `platform`: VARCHAR(191)
  - 理由: 将来的な拡張性（STRING型）
  - ENUM型は使用しない（トランザクションテーブルのため）

#### trx_diamond_balance（残高テーブル）

- `platform`: VARCHAR(191)
  - 理由: trx_diamondとの整合性
  
- `billing_platform`: VARCHAR(191)
  - 理由: 新しい決済プラットフォームの追加時にサービス停止を回避
  - ENUM型は使用しない（トランザクションテーブルのため）

#### mst_billing_platform_product（マスターテーブル）

- `billing_platform`: ENUM('AppStore', 'GooglePlay', 'PayPal', 'Stripe')
  - 理由: デプロイ時に計画的に更新可能
  - マスターテーブルのためENUM型を使用可

### チェックリスト

#### テーブル設計時

- [ ] trx_diamondは複合主キー `(sys_player_id, platform)` で定義
- [ ] trx_diamond_balanceは`id`を主キー、複合インデックス `(sys_player_id, platform, billing_platform)` を設定
- [ ] `billing_platform`はSTRING型で定義（トランザクションテーブル）
- [ ] `unit_price`カラムを追加（返金計算用）
- [ ] タイムスタンプカラム（created_at, updated_at）を設定

#### 購入処理実装時

- [ ] トランザクション内で両テーブルを更新
- [ ] trx_diamondの`paid_amount`または`free_amount`を加算
- [ ] trx_diamond_balanceに新規レコードを追加
- [ ] `current_amount`と`purchase_amount`に同じ値を設定
- [ ] `unit_price`を正確に記録

#### 消費処理実装時

- [ ] `id`の昇順でソート（FIFO保証）
- [ ] `where('current_amount', '>', 0)`で残高のあるレコードのみ取得
- [ ] プラットフォームと決済プラットフォームで絞り込み
- [ ] 順番に消費し、`current_amount`を更新
- [ ] trx_diamondの現在値も更新
- [ ] レコードは削除しない（履歴保持）

#### 返金処理実装時

- [ ] 該当する購入レコードの`current_amount`から返金額を計算
- [ ] `unit_price`を使用して正確な金額を算出
- [ ] 返金後、残高を0に更新（レコードは削除しない）
- [ ] trx_diamondの現在値も更新

---

## マスター・ディテールパターンのENUM型定義

### 概要

マスター・ディテール関係（親子関係）を持つテーブルにおいて、ENUM値やSTRING値は、システム全体で使用されている標準的な型定義（DeliveryConst等）と一致させる必要があります。

### 重要な原則

**全てのDB文字列値はスネークケースに統一する**

- mst/tol/admテーブルのENUM値はスネークケースで定義する
- sys/trx/logテーブルはENUMではなくSTRING型を使用し、値はスネークケース
- PHPの定数クラス・Enumクラスの値もスネークケースで定義する
- 大文字小文字の表記も完全に一致させる

### 具体例：ログインボーナステーブル

#### ❌ 誤った実装例

```php
// マスターテーブル: mst_login_bonus_content（ENUM値がパスカルケース）
Schema::connection('mst')->create('mst_login_bonus_content', function (Blueprint $table) {
    $table->enum('content_type', ['Item', 'Unit', 'Equipment', 'Diamond', 'Currency'])
        ->comment('コンテンツタイプ');
    // ❌ パスカルケース・Currencyという名称がDeliveryConstと不一致
});

// trxテーブルでENUMを使用（ENUMはtrxに使ってはいけない）
Schema::connection('trx1')->create('trx_login_bonus_history', function (Blueprint $table) {
    $table->enum('reward_type', ['Item', 'Unit', 'Equipment', 'Diamond', 'Currency'])
        ->comment('報酬タイプ');
    // ❌ trxテーブルにENUMは使用禁止・かつパスカルケース
});
```

**問題点:**
1. ENUM値がパスカルケース（`Item`, `Unit`）で、DeliveryConstのスネークケース（`item`, `unit`）と不一致
2. ENUMに`Currency`があるが、DeliveryConstでは`wallet`を使用
3. trxテーブルでENUMを使用している（ALTER時にサービス停止が必要になる）

#### ✅ 正しい実装例

```php
// マスターテーブル: mst_login_bonus_content（ENUMかつスネークケース）
Schema::connection('mst')->create('mst_login_bonus_content', function (Blueprint $table) {
    $table->enum('content_type', ['item', 'unit', 'equipment', 'diamond', 'wallet'])
        ->comment('コンテンツタイプ');
    // ✅ mstテーブルはENUM可・DeliveryConstと完全一致（スネークケース）
});

// trxテーブル: ENUMではなくSTRING（値はスネークケース）
Schema::connection('trx1')->create('trx_login_bonus_history', function (Blueprint $table) {
    $table->string('reward_type')
        ->comment('報酬タイプ (item, unit, equipment, diamond, wallet)');
    // ✅ trxテーブルはSTRING型・コメントで許容値を明記
});

// 配送システムの定数定義（スネークケース）
class DeliveryConst
{
    public const CONTENT_TYPE_ITEM = 'item';
    public const CONTENT_TYPE_UNIT = 'unit';
    public const CONTENT_TYPE_EQUIPMENT = 'equipment';
    public const CONTENT_TYPE_DIAMOND = 'diamond';
    public const CONTENT_TYPE_WALLET = 'wallet';
}
```

### 実装時のチェックリスト

マスター・ディテールパターンで実装する際は、以下を確認してください：

**マイグレーション作成時:**
- [ ] mst/tol/admテーブルのENUM値がスネークケースか
- [ ] sys/trx/logテーブルでENUMを使っていないか（STRING型を使用する）
- [ ] ENUM値・STRING値のラベルが定数（DeliveryConst等）と一致しているか
- [ ] 親テーブルと子テーブルで値が統一されているか

**既存マイグレーションの修正時:**
- [ ] 既存データがある場合は、データ移行スクリプトを作成
- [ ] ロールバック処理も正しく実装されているか
- [ ] テストデータで動作確認を行う

### 命名規則の統一

| 用途 | テーブル | 型 | カラム名 | 値のケース |
|------|---------|-----|---------|--------|
| コンテンツタイプ（mstマスター） | mst_*_content | ENUM | `content_type` | スネークケース（`item`, `unit`, `wallet`等） |
| 報酬タイプ（trx履歴） | trx_*_history | STRING | `reward_type` | スネークケース（`item`, `unit`, `wallet`等） |
| 課金プラットフォーム（mst） | mst_billing_* | ENUM | `billing_platform` | スネークケース（`app_store`, `google_play`等） |
| 課金プラットフォーム（trx） | trx_diamond_* | STRING | `billing_platform` | スネークケース（`app_store`, `google_play`等） |

### 注意事項

1. **ENUM値・STRING値は常にスネークケース**
   - DBに保存される全ての文字列値をスネークケースに統一
   - PHPの定数・Enumクラスの値も同様

2. **trx/sys/logテーブルはSTRING型**
   - ENUMの値追加にはALTERが必要でサービス停止を招く
   - STRING型なら値の追加はアプリケーション側の変更のみで対応可能

3. **マイグレーションのロールバック**
   - ENUM値を変更する場合、既存データの移行が必要
   - ロールバック時も元の状態に戻せるように実装

4. **複数データベースへの適用**
   - trx1とtrx2など、複数のシャードに同じマイグレーションを適用する場合は、すべてで統一

### 関連する標準定数

システム内で標準として使用されている定数クラス：

- `DeliveryConst`: 配送システムの型定義
- その他の定数クラスがある場合は、同様に参照すること

### トラブルシューティング

**"Data truncated for column 'content_type' at row X" エラーが発生した場合:**

1. ENUM定義を確認
   ```sql
   DESCRIBE mst_login_bonus_content;
   ```

2. 挿入しようとしている値を確認
   ```php
   // 'wallet'を挿入しようとしたが、ENUMに'currency'しか定義されていない
   ```

3. マイグレーションを修正してロールバック・再実行
   ```bash
   php artisan migrate:rollback --step=1
   php artisan migrate
   ```

