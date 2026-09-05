# API Design Guidelines

### Log Model の特別なルール

Log Model（`App\Models\Log\`）は、insert onlyのログテーブルを扱うため、以下の特別なルールがあります。

#### 基底クラス: `_BaseLog`

```php
abstract class _BaseLog extends Model implements _BaseLogInterface
{
    protected $connection = 'log';  // log DB接続を使用
    const UPDATED_AT = null;        // updated_atを無効化（ログは更新されない）
}
```

**主要な特徴**:
- `$connection = 'log'`: 専用のログデータベース接続を使用
- `const UPDATED_AT = null`: ログは更新されないため、`updated_at`カラムを無効化
- すべてのLog Modelはこの基底クラスを継承する

#### 実装ルール

**✅ 含めるべき内容**:
- `$casts`: ビジネスカラムと`system_at`のみ
- `$fillable`: ビジネスカラムと`system_at`のみ
- `_BaseLog`を継承

**❌ 含めてはいけない内容**:
- `$casts`に`created_at`, `updated_at`を含めない（Laravelが自動キャスト/存在しない）
- `$fillable`に`created_at`, `updated_at`を含めない（自動設定/存在しない）

#### 日時カラムの仕様

| カラム | 設定方法 | デバッグ時の日時変更との連動 | 用途 |
|--------|---------|---------------------------|------|
| `system_at` | APIから明示的に設定 | ✅ 連動する | ビジネスロジック用のタイムスタンプ |
| `created_at` | MySQL `CURRENT_TIMESTAMP` | ❌ 連動しない | 実際の記録時刻 |
| `updated_at` | なし（カラム自体が存在しない） | - | ログは更新されない |

**重要**: デバッグ時に日時を変更する機能がある場合、`system_at`はその変更に連動しますが、`created_at`は実際の記録時刻を保持します。これにより、デバッグ時と実際の記録時刻の両方を追跡できます。

#### 実装例: LogEquipment

```php
<?php

namespace App\Models\Log;

use App\Models\Log\Interfaces\_BaseLogInterface;

class LogEquipment extends _BaseLog implements _BaseLogInterface
{
    protected $connection = 'log';  // 継承元で設定済みだが明示的に記載可能
    protected $table = 'log_equipment';

    // ✅ ビジネスカラムとsystem_atのみ
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

    // ✅ ビジネスカラムとsystem_atのみ
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
        'system_at',  // APIから明示的に設定
    ];

    // ❌ created_at, updated_atは含めない
}
```

#### マイグレーションの例

```php
Schema::create('log_equipment', function (Blueprint $table) {
    $table->id();
    $table->string('unique_request_id', 36)->index();
    $table->unsignedBigInteger('sys_player_id')->index();
    $table->unsignedBigInteger('trx_equipment_id');
    $table->unsignedInteger('mst_equipment_id');
    $table->unsignedTinyInteger('before_grade');
    $table->unsignedTinyInteger('after_grade');
    $table->unsignedSmallInteger('before_level');
    $table->unsignedInteger('before_level_exp');
    $table->unsignedSmallInteger('after_level');
    $table->unsignedInteger('after_level_exp');
    $table->timestamp('system_at')->index();      // APIから設定
    $table->timestamp('created_at')->useCurrent(); // MySQL自動設定
    // updated_atカラムは作成しない
});
```

#### チェックリスト

Log Modelを実装・レビューする際は、以下を確認してください:

- [ ] `_BaseLog`を継承している
- [ ] `$connection = 'log'`が設定されている（継承元で設定済み）
- [ ] `$casts`にビジネスカラムと`system_at`のみが含まれている
- [ ] `$casts`に`created_at`, `updated_at`が含まれていない
- [ ] `$fillable`にビジネスカラムと`system_at`のみが含まれている
- [ ] `$fillable`に`created_at`, `updated_at`が含まれていない
- [ ] マイグレーションで`updated_at`カラムを作成していない
- [ ] マイグレーションで`created_at`に`useCurrent()`を設定している

#### 既存のLog Model一覧

このプロジェクトには以下のLog Modelが存在します:

- `LogAccess` - アクセスログ
- `LogPlayer` - プレイヤーレベル変更ログ
- `LogItem` - アイテム増減ログ
- `LogGacha` - ガチャ実行ログ
- `LogUnit` - ユニット取得・強化ログ
- `LogEquipment` - 装備取得・強化ログ
- `LogInAppPurchase` - 課金ログ

すべてのLog Modelは上記のルールに従って実装されています。


### データベース一覧

| データベース | コンテナ名 | ポート | 用途 | 接続名 |
|------------|-----------|--------|------|--------|
| admin | db-adm | 63060 | 管理画面用データ | `admin` |
| tool | db-tol | 63061 | 運営ツール機能 | `tool` |
| sys | db-sys | 63062 | システム管理（デプロイ、シャーディング） | `sys` |
| mst | db-mst | 63063 | マスターデータ（キャラ、アイテム等） | `mst` |
| log | db-log | 63261 | ログデータ | `log` |
| trx1 | db-trx1 | 63161 | トランザクションデータ（シャード1） | `trx1` |
| trx2 | db-trx2 | 63162 | トランザクションデータ（シャード2） | `trx2` |

### データベースの役割

#### admin データベース
- 管理画面のユーザー、権限管理
- 管理画面用のログ、監査証跡

#### sys データベース
- **システム全体の管理**: 全シャード共通の設定
- **プレイヤーID採番**: `sys_player`テーブルで一元管理
- **シャーディング管理**: どのプレイヤーがどのシャードに属するか
- **デプロイ管理**: マスターデータとアセットのバージョン管理

#### mst データベース
- ゲームマスターデータ（キャラクター、アイテム、クエスト等）
- 全シャード共通（読み取り専用）
- `deploy_key`でバージョン管理

#### log データベース
- アクセスログ、エラーログ
- プレイヤー行動ログ
- 全シャードのログを集約

#### trx1, trx2 データベース（シャード）
- プレイヤーのトランザクションデータ（所持アイテム、進行状況等）
- プレイヤーごとにシャードが割り当てられる
- 水平分割により負荷分散

## プレイヤー識別子の使い分け

### 基本ルール

**セキュリティレベルに応じてuuid、my_id、idを使い分ける**

### 識別子の種類と用途

#### 1. `uuid` (string, 36文字) - セキュアなAPI用

- **用途**: 改竄されたら問題がある重要なAPI
- **セキュリティレベル**: 高
- **例**: 
  - 課金API（ダイヤ購入、パック購入）
  - リソース付与API（アイテム付与、ユニット付与）
  - プレイヤー情報変更API（名前変更、設定変更）
  - 報酬受取API（メールボックス報酬受取）

**理由**:
- 予測不可能: UUIDはランダム生成で総当たり攻撃が困難
- 改竄防止: 他人のuuidを推測して不正操作することが事実上不可能
- グローバル一意性: 外部システム連携にも対応

**例: APIリクエスト（課金API）**
```json
POST /api/in_app_purchase/buy
{
  "uuid": "bccc489b-9f4f-4ebb-b63a-02fa9259c74d",
  "product_id": "diamond_pack_100"
}
```

#### 2. `my_id` (string, 8文字) - ユーザーフレンドリーなAPI用

- **用途**: 改竄されても大きな問題がない社交的なAPI
- **セキュリティレベル**: 中
- **例**:
  - フレンド検索（my_idで他プレイヤーを検索）
  - フレンド申請送信
  - プレイヤー検索
  - ギルド招待

**理由**:
- ユーザーフレンドリー: 8桁で覚えやすい、口頭で伝えやすい
- 紛らわしい文字除外: I/l/O/0を除外（58文字セット）
- 画面表示に最適: プレイヤーカードやフレンドリストに表示
- カスタマーサポート: 問い合わせ時のID特定に使用

**文字セット**:
- 大文字: `ABCDEFGHJKLMNPQRSTUVWXYZ` (24文字 - IとOを除外)
- 小文字: `abcdefghijkmnopqrstuvwxyz` (25文字 - lを除外)
- 数字: `123456789` (9文字 - 0を除外)
- 合計: 58文字 → 8桁で128兆パターン

**例: APIリクエスト（フレンド申請）**
```json
POST /api/friend/apply/send
{
  "my_id": "Ab3Xy9Kp"
}
```

**例: 画面表示**
```
┌─────────────────────┐
│ プレイヤー情報       │
├─────────────────────┤
│ ID: Ab3Xy9Kp        │
│ 名前: Player123     │
│ レベル: 50          │
└─────────────────────┘
```

#### 3. `id` (bigint) - 内部処理用

- **用途**: API内部、DB操作、内部ロジック
- **セキュリティレベル**: 内部のみ（外部に公開しない）

**理由**:
- パフォーマンス: 数値型の検索・JOIN が高速
- ストレージ効率: bigint(8バイト) < UUID(36バイト)
- インデックス効率: 数値型インデックスが効率的
- AUTO_INCREMENT: IDの自動採番が可能

**例: 内部処理**
```php
// my_idでプレイヤーを検索（フレンド申請）
public function sendFriendRequest(string $myId)
{
    // my_idからIDに変換
    $targetPlayer = SysPlayer::where('my_id', $myId)->firstOrFail();
    $targetPlayerId = $targetPlayer->id;
    
    // 内部処理はIDで実行
    $friendApply = new SysFriendApply();
    $friendApply->sender_sys_player_id = Auth::id();
    $friendApply->receiver_sys_player_id = $targetPlayerId;
    $friendApply->save();
}

// uuidでプレイヤーを検索（課金API）
public function buyDiamond(string $uuid, string $productId)
{
    // uuidからIDに変換
    $player = SysPlayer::where('uuid', $uuid)->firstOrFail();
    $playerId = $player->id;
    
    // 内部処理はIDで実行
    $this->purchaseService->processPurchase($playerId, $productId);
}
```

### データベース設計

#### sys_player テーブル
```sql
CREATE TABLE sys_player (
    id bigint PRIMARY KEY AUTO_INCREMENT,  -- 内部用ID（全シャード共通で一元採番）
    uuid varchar(191) UNIQUE,               -- 外部API用UUID
    my_id varchar(8) UNIQUE,                -- サポート用ID（8桁、紛らわしい文字除外）
    name varchar(191) UNIQUE,               -- プレイヤー名
    level int unsigned,
    level_exp int unsigned,
    rank int unsigned,
    login_type enum('guest','password','sns'),
    password varchar(191) NULL,
    last_login_at datetime NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### トランザクションテーブル（trx_*）

**設計原則**: 
- プレイヤーIDは`sys_player`テーブルで一元採番
- 不要なauto_incrementカラムは作成しない
- 複合PRIMARY KEYで一意性を保証できる場合は、idカラムを持たない

```sql
-- 1. trx_player（1プレイヤー = 1レコード）
CREATE TABLE trx_player (
    sys_player_id bigint PRIMARY KEY,  -- idカラム不要
    created_at datetime,
    updated_at datetime
);

-- 2. trx_unit（同じキャラを複数所持可能）
CREATE TABLE trx_unit (
    id bigint PRIMARY KEY AUTO_INCREMENT,  -- idカラム必要（個別インスタンス管理）
    sys_player_id bigint,
    mst_unit_id varchar(191),
    grade int unsigned,
    level int unsigned,
    skill1_level int unsigned DEFAULT 1,
    skill2_level int unsigned DEFAULT 1,
    skill3_level int unsigned DEFAULT 1,
    skill4_level int unsigned DEFAULT 1,
    skill5_level int unsigned DEFAULT 1,
    created_at datetime,
    updated_at datetime,
    INDEX idx_sys_player_id_mst_unit_id (sys_player_id, mst_unit_id)
);

-- 3. trx_item（sys_player_id + mst_item_idでユニーク）
CREATE TABLE trx_item (
    sys_player_id bigint,               -- idカラム不要（複合PRIMARY KEY）
    mst_item_id varchar(191),
    amount int unsigned,
    created_at datetime,
    updated_at datetime,
    PRIMARY KEY (sys_player_id, mst_item_id)
);

-- 4. trx_device（複数デバイス対応）
CREATE TABLE trx_device (
    sys_player_id bigint,               -- idカラム不要（複合PRIMARY KEY）
    device_uuid varchar(191),
    last_login_at datetime NULL,
    created_at datetime,
    updated_at datetime,
    PRIMARY KEY (sys_player_id, device_uuid)
);

-- 5. trx_player_sns（1プレイヤー = 1SNSタイプ）
CREATE TABLE trx_player_sns (
    sys_player_id bigint,               -- idカラム不要（複合PRIMARY KEY）
    sns_type enum('apple', 'google', 'x', 'facebook'),
    sns_user_id varchar(191),
    auth varchar(191),
    created_at datetime,
    updated_at datetime,
    PRIMARY KEY (sys_player_id, sns_type),
    INDEX idx_sns_type_sns_user_id (sns_type, sns_user_id)  -- 逆引き用
);
```

**idカラムの必要性判断**:

| 条件 | idカラム | 例 |
|-----|---------|---|
| 1レコード = 1プレイヤー | **不要** | trx_player |
| 複合キーでユニーク | **不要** | trx_item, trx_device, trx_player_sns |
| 同じ組み合わせで複数レコード必要 | **必要** | trx_unit（同じキャラを複数所持） |

**メリット**:
1. 不要なカラム削減 → ストレージ効率化
2. 複合PRIMARY KEYで一意性保証 → ユニーク制約が自明
3. シャード統合時のIDコンフリクト回避

### 実装パターン

#### パターン1: コントローラー層での変換

```php
class PlayerController extends Controller
{
    public function show(string $uuid)
    {
        // 1. UUIDからプレイヤーを取得
        $player = SysPlayer::where('uuid', $uuid)->firstOrFail();
        
        // 2. 内部処理はIDを使用
        $this->playerService->updatePlayerData($player->id);
        
        // 3. レスポンスはUUIDを含める
        return response()->json([
            'uuid' => $player->uuid,
            'name' => $player->name,
        ]);
    }
}
```

#### パターン2: サービス層での分離

```php
class PlayerService
{
    // サービス層はIDで処理
    public function getPlayerData(int $playerId): array
    {
        $player = SysPlayer::findOrFail($playerId);
        $items = TrxItem::where('sys_player_id', $playerId)->get();
        
        return [
            'player' => $player,
            'items' => $items,
        ];
    }
}
```

### セキュリティ上の注意

1. **UUIDのみを公開**: 連番IDは絶対に外部に公開しない
2. **予測不可能**: UUID v4はランダム生成で推測不可能
3. **列挙攻撃の防止**: 連番IDと異なり、総ユーザー数の推測が困難
4. **内部IDの隠蔽**: データベースの内部構造を隠蔽

### パフォーマンス最適化

1. **UUIDにインデックス**: 外部からの検索用
   ```sql
   CREATE INDEX idx_uuid ON sys_player(uuid);
   ```

2. **IDは PRIMARY KEY**: 内部結合は数値IDで高速化
   ```sql
   -- 高速: 数値型のJOIN
   SELECT * FROM trx_item WHERE sys_player_id = 123;
   
   -- 低速: 文字列型のJOIN（使わない）
   SELECT * FROM trx_item WHERE player_uuid = 'bccc489b-...';
   ```

### データベース設計

#### sys_player テーブル
```sql
CREATE TABLE sys_player (
    id bigint PRIMARY KEY AUTO_INCREMENT,  -- 内部用ID（全シャード共通で一元採番）
    uuid varchar(191) UNIQUE,               -- セキュアなAPI用UUID（36文字）
    my_id varchar(8) UNIQUE,                -- ユーザーフレンドリーなID（8桁、紛らわしい文字除外）
    name varchar(191) UNIQUE,               -- プレイヤー名
    level int unsigned,
    level_exp int unsigned,
    last_login_at datetime NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### APIによる使い分け例

| API | 識別子 | 理由 |
|-----|--------|------|
| 課金（ダイヤ購入） | `uuid` | 改竄されると金銭的損失 |
| 課金（パック購入） | `uuid` | 改竄されると金銭的損失 |
| メールボックス報酬受取 | `uuid` | 不正にアイテム取得される |
| ガチャ実行 | `uuid` | 改竄されるとリソース不正取得 |
| ユニット強化 | `uuid` | 改竄されると不正強化 |
| フレンド申請送信 | `my_id` | 他プレイヤーを探す用途 |
| フレンド申請承認/却下 | トークンで認証 | 自分宛ての申請のみ操作可能 |
| プレイヤー検索 | `my_id` | 他プレイヤーを探す用途 |
| ログイン | トークンで認証 | 認証済みプレイヤーのみ |
| プレイヤー情報取得 | トークンで認証 | 自分の情報のみ取得可能 |

### セキュリティ設計の原則

1. **改竄リスクの高いAPI**: `uuid`を使用
   - リソース付与、消費系
   - 金銭に関わる処理
   - プレイヤーデータ変更

2. **社交的なAPI**: `my_id`を使用
   - フレンド機能
   - プレイヤー検索
   - ギルド招待

3. **認証済み操作**: トークンで認証
   - 自分のデータのみ操作
   - リクエストにuuid/my_idを含めない
   - ミドルウェアでプレイヤーIDを取得

### 実装パターン

#### パターン1: uuid使用（課金API）

```php
// ❌ Bad: 推測可能なIDを使用
POST /api/in_app_purchase/buy
{
  "player_id": 12345,  // 連番IDは推測されやすい
  "product_id": "diamond_100"
}

// ✅ Good: uuidを使用
POST /api/in_app_purchase/buy
{
  "uuid": "bccc489b-9f4f-4ebb-b63a-02fa9259c74d",  // 推測不可能
  "product_id": "diamond_100"
}
```

#### パターン2: my_id使用（フレンド申請）

```php
// ✅ Good: my_idを使用
POST /api/friend/apply/send
{
  "my_id": "Ab3Xy9Kp"  // ユーザーフレンドリー
}

// コントローラー実装
public function sendFriendRequest(ApplySendRequest $request)
{
    $myId = $request->getMyId();
    $targetPlayer = SysPlayer::where('my_id', $myId)->firstOrFail();
    
    // 送信者は認証トークンから取得
    $senderPlayerId = Auth::id();
    
    // フレンド申請作成
    $this->friendService->sendApply($senderPlayerId, $targetPlayer->id);
}
```

#### パターン3: トークン認証（ログイン後API）

```php
// ✅ Good: トークンで認証
POST /api/player/me
// リクエストボディなし、トークンのみ

// コントローラー実装
public function me(MeRequest $request)
{
    // ミドルウェアで認証済みプレイヤーIDを取得
    $sysPlayerId = $request->getAuthenticatedPlayerId();
    
    // 自分の情報のみ取得
    return $this->playerService->getPlayerData($sysPlayerId);
}
```

### パフォーマンス最適化

1. **両方にインデックスを作成**:
   ```sql
   CREATE UNIQUE INDEX idx_uuid ON sys_player(uuid);
   CREATE UNIQUE INDEX idx_my_id ON sys_player(my_id);
   ```

2. **内部処理は必ずIDを使用**:
   ```sql
   -- 高速: 数値型のJOIN
   SELECT * FROM trx_item WHERE sys_player_id = 123;
   
   -- 低速: 文字列型のJOIN（使わない）
   SELECT * FROM trx_item WHERE player_uuid = 'bccc489b-...';
   SELECT * FROM trx_item WHERE player_my_id = 'Ab3Xy9Kp';
   ```

### セキュリティ上の注意

1. **uuidは予測不可能**: UUID v4はランダム生成で総当たり攻撃が困難
2. **my_idは短いが十分**: 8桁58文字セットで128兆パターン（衝突確率は極めて低い）
3. **内部IDは絶対に公開しない**: 連番IDは総ユーザー数などが推測可能
4. **トークン認証を優先**: 可能な限りトークンで認証し、識別子をリクエストに含めない

### まとめ

| 用途 | 識別子 | 型 | セキュリティ | 用途例 |
|-----|--------|----|-----------|----|
| セキュアなAPI | `uuid` | string (36文字) | 高 | 課金、報酬受取、リソース操作 |
| 社交的なAPI | `my_id` | string (8文字) | 中 | フレンド検索、プレイヤー検索 |
| 内部処理 | `id` | bigint | 内部のみ | DB検索、JOIN、内部ロジック |
| 外部DB参照 | `sys_player_id` | bigint | 内部のみ | trx_*, log_* テーブルの外部キー |

**原則**: セキュリティレベルに応じて使い分け、境界（API層）で変換し、内部は効率的に。

## シャーディング設計

### 基本方針

**完全静的管理**: シャード接続情報は`config/database.php`で静的管理し、データベースには数値のみ保存。

### シャーディングテーブル構造

#### sys_sharding（シャーディング設定）
```sql
CREATE TABLE sys_sharding (
    id int PRIMARY KEY AUTO_INCREMENT,
    name varchar(191) UNIQUE,              -- 'player' など
    strategy enum('hash','range','list'),  -- 分割戦略
    status enum('active','inactive'),
    created_at datetime,
    updated_at datetime
);
```

#### sys_sharding_node（シャードノード管理）
```sql
CREATE TABLE sys_sharding_node (
    id int PRIMARY KEY AUTO_INCREMENT,
    sharding_id int,                       -- sys_sharding.id
    node_no int,                           -- 1, 2, 3... （接続名は trx{node_no}）
    status enum('active','inactive'),
    weight int,                            -- 負荷分散の重み
    created_at datetime,
    updated_at datetime
);
```

**重要**: `node_no`は数値のみ（1, 2, 3...）。接続名は`trx{node_no}`で動的構築（例: `trx1`, `trx2`）。

#### sys_sharding_node_player（プレイヤー割り当て）
```sql
CREATE TABLE sys_sharding_node_player (
    sys_player_id bigint PRIMARY KEY,      -- プレイヤーID（1プレイヤー1ノード）
    sharding_node_id int,                  -- sys_sharding_node.id
    assigned_at datetime,
    INDEX idx_sharding_node_id (sharding_node_id)
);
```

### シャーディングの実装パターン

```php
// プレイヤーのシャードを取得
$player = SysPlayer::findByUuid($uuid);
$nodePlayer = SysShardingNodePlayer::findBySysPlayerId($player->id);
$node = $nodePlayer->shardingNode;

// 動的に接続名を構築
$connectionName = $node->getTrxConnectionName(); // "trx1" or "trx2"

// シャード固有のデータを取得
$trxPlayer = TrxPlayer::on($connectionName)
    ->where('sys_player_id', $player->id)
    ->first();
```

### プレイヤーID採番の一元管理

**設計思想**: 全シャード共通でプレイヤーIDを採番し、将来的なシャード統合時のIDコンフリクトを回避する。

#### Before（旧設計 - 各シャードで独立採番）
```sql
-- 各シャードで独立してIDを採番（統合時に衝突リスク）
trx1.trx_player: id=1, id=2, id=3...
trx2.trx_player: id=1, id=2, id=3...  ← 統合時に衝突
```

#### After（新設計 - sysで一元採番）
```sql
-- sysで一元採番
sys.sys_player: id=1, id=2, id=3, id=4, id=5, id=6...

-- 各シャードのtrx_playerはsys_player_idをPRIMARY KEYとして使用
trx1.trx_player: sys_player_id=1, sys_player_id=3, sys_player_id=5...
trx2.trx_player: sys_player_id=2, sys_player_id=4, sys_player_id=6...
```

**メリット**:
1. シャード間でIDが重複しない
2. 将来のシャード統合が容易
3. プレイヤーのシャード移動が可能
4. グローバルにユニークなID体系

## Deploy Key管理

### Deploy Key仕様

- **型**: `INT` (10桁)
- **形式**: `YYYYMMDDN` (N=1日の中での連番)
- **例**: 
  - `202601011` = 2026年1月1日の1回目
  - `202601012` = 2026年1月1日の2回目
  - `202601021` = 2026年1月2日の1回目

### マスターデータのバージョン管理

全てのマスターテーブルに`deploy_key`カラムを追加し、デプロイ単位でバージョン管理を行う。

```sql
-- マスターテーブルの例
CREATE TABLE mst_character (
    id int PRIMARY KEY,
    name varchar(191),
    deploy_key int,                        -- デプロイキー
    ...
);
```

### デプロイ管理テーブル

#### sys_deploy_master（マスターデータデプロイ履歴）
```sql
CREATE TABLE sys_deploy_master (
    deploy_key int PRIMARY KEY,
    version varchar(191),
    status enum('pending','active','inactive'),
    deployed_at datetime,
    created_at datetime,
    updated_at datetime
);
```

#### sys_deploy_master_schedule（マスターデータデプロイスケジュール）
```sql
CREATE TABLE sys_deploy_master_schedule (
    id int PRIMARY KEY AUTO_INCREMENT,
    deploy_key int,
    scheduled_at datetime,
    status enum('pending','completed','cancelled'),
    executed_at datetime NULL,
    created_at datetime,
    updated_at datetime
);
```

#### sys_deploy_asset（アセットデプロイ履歴）
```sql
CREATE TABLE sys_deploy_asset (
    deploy_key int PRIMARY KEY,
    version varchar(191),
    asset_url varchar(191),
    status enum('pending','active','inactive'),
    deployed_at datetime,
    created_at datetime,
    updated_at datetime
);
```

#### sys_deploy_asset_schedule（アセットデプロイスケジュール）
```sql
CREATE TABLE sys_deploy_asset_schedule (
    id int PRIMARY KEY AUTO_INCREMENT,
    deploy_key int,
    scheduled_at datetime,
    status enum('pending','completed','cancelled'),
    executed_at datetime NULL,
    created_at datetime,
    updated_at datetime
);
```

### デプロイフロー

1. **マスターデータ準備**: 新しい`deploy_key`でマスターデータを投入
2. **スケジュール登録**: `sys_deploy_master_schedule`に公開予定を登録
3. **自動切り替え**: スケジュール時刻に達したら`status='active'`に変更
4. **クライアント更新**: アプリが新しい`deploy_key`のデータを参照

## インデックス設計のベストプラクティス

### UNIQUE制約とINDEXの関係

**重要**: `unique()`制約を設定すると自動的にインデックスが作成されるため、追加で`index()`を呼ぶと重複インデックスが作成される。

#### 悪い例（重複インデックス）
```php
Schema::create('sys_player', function (Blueprint $table) {
    $table->string('uuid')->unique();  // sys_player_uuid_unique が作成される
    $table->index('uuid');              // sys_player_uuid_index も作成（重複）
});
```

#### 良い例
```php
Schema::create('sys_player', function (Blueprint $table) {
    $table->string('uuid')->unique();  // これだけでインデックスも作成される
});
```

### インデックス設計の原則

1. **PRIMARY KEY**: 自動的にインデックス作成
2. **UNIQUE制約**: 自動的にインデックス作成
3. **外部キー**: 明示的に`index()`を追加
4. **検索条件**: WHERE句で使うカラムに`index()`を追加
5. **ソート**: ORDER BY句で使うカラムに`index()`を追加

#### sys_player テーブルのインデックス例
```php
Schema::create('sys_player', function (Blueprint $table) {
    $table->id();                           // PRIMARY KEY (自動)
    $table->string('uuid')->unique();       // UNIQUE INDEX (自動)
    $table->string('my_id', 8)->unique();   // UNIQUE INDEX (自動)
    $table->string('name')->unique();       // UNIQUE INDEX (自動)
    $table->datetime('last_login_at')->nullable();
    $table->index('last_login_at');         // 検索/ソート用（明示的）
});
```

**結果**:
- `PRIMARY KEY`: `id`
- `UNIQUE INDEX`: `uuid`, `my_id`, `name`
- `INDEX`: `last_login_at`

## Eloquentモデルの設計ルール

### ディレクトリ構造

モデルはデータベースごとにディレクトリを分けて管理する。

```
app/Models/
├── Sys/          # sysデータベース用
│   ├── SysPlayer.php
│   ├── SysSharding.php
│   ├── SysShardingNode.php
│   └── ...
├── Mst/          # mstデータベース用
│   ├── MstCharacter.php
│   ├── MstItem.php
│   └── ...
├── Trx/          # trx1, trx2データベース用
│   ├── TrxPlayer.php
│   ├── TrxItem.php
│   ├── TrxUnit.php
│   └── ...
└── Log/          # logデータベース用
```

### データベース接続の指定

#### 単一データベース用モデル（sys, mst, log）

```php
namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysPlayer extends Model
{
    /**
     * データベース接続名
     */
    protected $connection = 'sys';
    
    /**
     * テーブル名
     */
    protected $table = 'sys_player';
    
    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'uuid',
        'my_id',
        'name',
        'level',
        'level_exp',
        'rank',
        'login_type',
        'password',
        'last_login_at',
    ];
}
```

#### 動的接続切り替え用モデル（trx）

trxモデルは、プレイヤーのシャード割り当てに応じて動的に接続を切り替える。

```php
namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Model;

class TrxPlayer extends Model
{
    /**
     * デフォルトのデータベース接続名
     * 実行時に動的に切り替える
     */
    protected $connection = 'trx1';  // デフォルト
    
    /**
     * テーブル名
     */
    protected $table = 'trx_player';
    
    /**
     * プライマリキー
     */
    protected $primaryKey = 'sys_player_id';
    
    /**
     * auto_incrementを使用しない
     */
    public $incrementing = false;
    
    /**
     * プライマリキーの型
     */
    protected $keyType = 'int';
}
```

**使用例**:
```php
// 動的に接続を切り替え
$connectionName = 'trx2';
$trxPlayer = TrxPlayer::on($connectionName)
    ->where('sys_player_id', $playerId)
    ->first();
```

### 複合PRIMARY KEYを持つモデル

複合PRIMARY KEYを持つテーブル（trx_item, trx_device, trx_player_sns）のモデル設計。

#### TrxItem（sys_player_id + mst_item_id）

```php
namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Model;

class TrxItem extends Model
{
    protected $connection = 'trx1';
    protected $table = 'trx_item';
    
    /**
     * 複合PRIMARY KEYの場合、primaryKeyはnullまたは最初のキー
     */
    protected $primaryKey = null;
    
    /**
     * auto_incrementを使用しない
     */
    public $incrementing = false;
    
    protected $fillable = [
        'sys_player_id',
        'mst_item_id',
        'amount',
    ];
    
    /**
     * 複合キーでレコードを検索
     */
    public static function findByCompositeKey(int $sysPlayerId, string $mstItemId)
    {
        return self::where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();
    }
    
    /**
     * 複合キーでレコードを作成または更新
     */
    public static function createOrUpdateByCompositeKey(int $sysPlayerId, string $mstItemId, int $amount)
    {
        return self::updateOrCreate(
            [
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
            ],
            ['amount' => $amount]
        );
    }
}
```

#### TrxDevice（sys_player_id + device_uuid）

```php
namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Model;

class TrxDevice extends Model
{
    protected $connection = 'trx1';
    protected $table = 'trx_device';
    protected $primaryKey = null;
    public $incrementing = false;
    
    protected $fillable = [
        'sys_player_id',
        'device_uuid',
        'last_login_at',
    ];
    
    public static function findByCompositeKey(int $sysPlayerId, string $deviceUuid)
    {
        return self::where('sys_player_id', $sysPlayerId)
            ->where('device_uuid', $deviceUuid)
            ->first();
    }
}
```

#### TrxPlayerSns（sys_player_id + sns_type）

```php
namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Model;

class TrxPlayerSns extends Model
{
    protected $connection = 'trx1';
    protected $table = 'trx_player_sns';
    protected $primaryKey = null;
    public $incrementing = false;
    
    protected $fillable = [
        'sys_player_id',
        'sns_type',
        'sns_user_id',
        'auth',
    ];
    
    public static function findByCompositeKey(int $sysPlayerId, string $snsType)
    {
        return self::where('sys_player_id', $sysPlayerId)
            ->where('sns_type', $snsType)
            ->first();
    }
    
    /**
     * SNSユーザーIDで逆引き検索
     */
    public static function findBySnsUserId(string $snsType, string $snsUserId)
    {
        return self::where('sns_type', $snsType)
            ->where('sns_user_id', $snsUserId)
            ->first();
    }
}
```

### リレーションの定義

外部キー制約がなくても、Eloquentのリレーションは通常通り定義できる。

```php
// TrxPlayer.php
public function sysPlayer(): BelongsTo
{
    // 異なるデータベース間のリレーション
    // 外部キー制約はないが、論理的な関連は定義可能
    return $this->belongsTo(SysPlayer::class, 'sys_player_id', 'id');
}

// TrxItem.php
public function mstItem(): BelongsTo
{
    return $this->belongsTo(MstItem::class, 'mst_item_id', 'id');
}
```

**注意**: 異なるデータベース間のリレーション（例: trx → sys）は、JOIN が実行されず、N+1クエリになる可能性があるため、Eager Loadingには注意が必要。

### UUID・my_idの自動生成

SysPlayerモデルでUUIDとmy_idを自動生成する。

```php
namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SysPlayer extends Model
{
    protected $connection = 'sys';
    protected $table = 'sys_player';
    
    /**
     * モデルの起動メソッド
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            // UUIDの自動生成
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            
            // my_idの自動生成
            if (empty($model->my_id)) {
                $model->my_id = self::generateUniqueMyId();
            }
        });
    }
    
    /**
     * ユニークなmy_idを生成
     */
    private static function generateUniqueMyId(int $maxAttempts = 10): string
    {
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz123456789';
        $charsetLength = strlen($charset);
        $length = 8;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $myId = '';
            for ($i = 0; $i < $length; $i++) {
                $myId .= $charset[random_int(0, $charsetLength - 1)];
            }

            // 衝突チェック
            if (!self::where('my_id', $myId)->exists()) {
                return $myId;
            }
        }

        throw new \RuntimeException('Failed to generate unique my_id');
    }
    
    /**
     * my_idで検索
     */
    public static function findByMyId(string $myId)
    {
        return self::where('my_id', $myId)->first();
    }
}
```

### モデル設計のまとめ

| 項目 | ルール | 例 |
|-----|--------|---|
| ディレクトリ | データベースごとに分離 | `Models/Sys/`, `Models/Trx/` |
| $connection | 明示的に指定 | `protected $connection = 'sys';` |
| $table | テーブル名を明示 | `protected $table = 'sys_player';` |
| 複合PRIMARY KEY | `$primaryKey = null`, `$incrementing = false` | TrxItem, TrxDevice |
| 外部キー参照 | `{参照先テーブル名}_id` | `sys_player_id`, `mst_item_id` |
| リレーション | 外部キー制約なしでも定義可能 | `belongsTo()`, `hasMany()` |

### Eloquent内部メソッドとの衝突回避

Eloquentモデルでカスタムメソッドを作成する際、Eloquentの内部メソッドと衝突しないように注意する。

#### 衝突例

```php
// ❌ 悪い例: Eloquentの内部メソッドと衝突
class SysShardingNode extends Model
{
    // getConnectionName()はEloquentの内部メソッド
    // モデル保存時に誤った接続が使用される
    public function getConnectionName(): string
    {
        return "trx{$this->node_no}";
    }
}
```

**問題**: `getConnectionName()`はEloquentが使用する内部メソッドのため、モデル保存時にこのメソッドが呼ばれ、誤った接続が使用されてしまう。

#### 解決策

```php
// ✅ 良い例: より具体的な名前で衝突を回避
class SysShardingNode extends Model
{
    // より具体的な名前にして衝突を回避
    public function getTrxConnectionName(): string
    {
        return "trx{$this->node_no}";
    }
}
```

#### 避けるべきメソッド名パターン

Eloquentが使用する可能性があるメソッド名パターン:

- `getConnection*()` - 接続関連
- `getTable*()` - テーブル関連
- `get*Attribute()` - アクセサ（Eloquentの機能として予約）
- `set*Attribute()` - ミューテータ（Eloquentの機能として予約）
- `scope*()` - クエリスコープ（Eloquentの機能として予約）

**推奨**: カスタムメソッドには、より具体的で目的が明確な名前を付ける。

```php
// ✅ 良い例
getTrxConnectionName()      // 具体的
getShardConnectionName()    // 具体的
buildConnectionName()       // 具体的

// ❌ 避けるべき
getConnectionName()         // Eloquent内部と衝突
getConnection()             // Eloquent内部と衝突
```

---

## API設計

### ルーティング規約

このプロジェクトでは、明確で保守しやすいAPIを提供するため、RESTful設計とアクションベースのルーティングを採用しています。

#### RESTful設計の原則

**✅ Good: アクションベースのルーティング**

```php
// 各アクションに専用のエンドポイントを定義
Route::post('/auth/sign_in', [AuthController::class, 'signIn']);
Route::post('/auth/sign_up', [AuthController::class, 'signUp']);
Route::post('/auth/version', [AuthController::class, 'version']);
```

**メリット:**
- 各エンドポイントの責務が明確
- コントローラーメソッドが単一責任
- ルーティングの可読性が高い
- APIドキュメントが自動生成しやすい

**❌ Bad: 一つのメソッドで全アクション処理**

```php
// すべてのアクションを一つのエンドポイントで処理
Route::post('/auth/{action}', [AuthController::class, 'handle']);
```

**問題点:**
- コントローラーメソッドが肥大化
- ルーティングだけでは機能が分からない
- バリデーションやミドルウェアの適用が複雑
- テストが困難

#### ルーティング規約

1. **1つのエンドポイント = 1つのControllerメソッド**
   - 各エンドポイントは専用のControllerメソッドにマッピング
   - コントローラーメソッド名はエンドポイントのアクションを反映

2. **HTTPメソッドを適切に使用**
   - `GET`: リソースの取得（一覧、詳細）
   - `POST`: リソースの作成、アクション実行
   - `PUT`/`PATCH`: リソースの更新
   - `DELETE`: リソースの削除

3. **リソース名は複数形**
   - 英語の場合: `/players`, `/items`, `/equipments`
   - 日本語の場合: 状況に応じて単数形も許容

#### URLパスの構造

```
/api/{resource}/{action}
/api/{resource}/{id}
/api/{resource}/{id}/{sub-resource}
```

**例:**

```php
// リソース + アクション
Route::post('/auth/sign_in', [AuthController::class, 'signIn']);
Route::post('/auth/sign_up', [AuthController::class, 'signUp']);

// リソース + ID
Route::get('/players/{id}', [PlayerController::class, 'show']);
Route::put('/players/{id}', [PlayerController::class, 'update']);

// リソース + ID + サブリソース
Route::get('/players/{id}/items', [PlayerItemController::class, 'index']);
```

#### バージョニング

将来的なAPIの変更に対応するため、プレフィックスでバージョン管理を行います。

```php
// v1 APIグループ
Route::prefix('v1')->group(function () {
    Route::post('/auth/version', [AuthController::class, 'version']);
    Route::post('/auth/sign_in', [AuthController::class, 'signIn']);
});

// v2 APIグループ（将来的に）
Route::prefix('v2')->group(function () {
    Route::post('/auth/version', [V2\AuthController::class, 'version']);
});
```

**バージョニング戦略:**
- 破壊的な変更がある場合のみ新しいバージョンを作成
- v1は後方互換性を保ちながら段階的に非推奨化
- クライアントに十分な移行期間を提供

### レスポンス形式

このプロジェクトでは、一貫性のあるJSONレスポンス形式を採用しています。

#### APIキーケースの方針

**重要: このプロジェクトではスネークケース（snake_case）を採用します**

```json
{
  "needs_update": true,
  "latest_deploy_id": 1,
  "master": {
    "deploy_master_id": 3,
    "hash": "430fe9e35ab4660c35127cb6d7425aaf9c2b4d3d1868a5845d1a96d9409a1736"
  }
}
```

**採用理由:**

1. **データベース一貫性**: DBカラム名と完全一致（`trx_unit`, `mst_item_id`など）
2. **Laravel標準**: Laravelのデフォルトがスネークケース
3. **変換コスト削減**: サーバー側で変換処理が不要
4. **RFC 8927準拠**: JSON API仕様でスネークケースを推奨
5. **可読性**: アンダースコアで単語が区切られて読みやすい

#### 配列の命名規則

**重要: 複数形の代わりに `_list` サフィックスを使用します**

日本人エンジニアにとって英語の複数形変換は難しく、ミスが発生しやすいため、配列には一律で `_list` サフィックスを使用します。

```json
{
  "trx_unit_list": [...],      // ✅ Good: _list サフィックス
  "trx_item_list": [...],      // ✅ Good: _list サフィックス
  "trx_wallet_list": [...],    // ✅ Good: _list サフィックス
  "login_bonus_list": [...]    // ✅ Good: _list サフィックス
}
```

```json
{
  "trx_units": [...],          // ❌ Bad: 複数形
  "trx_wallets": [...],        // ❌ Bad: 複数形
  "trx_item": [...],           // ❌ Bad: 単数形（配列なのに）
}
```

**理由:**

1. **複数形変換が不要**: unit → units, item → items, wallet → wallets など覚える必要がない
2. **一貫性**: すべての配列に同じルールを適用できる
3. **明確性**: `_list` サフィックスで配列であることが一目でわかる
4. **ミス防止**: equipment → equipments? equipment? で迷わない

**例外:**

- 配列ではない場合は `_list` を付けない
- 単一オブジェクトは単数形（例: `sys_player`, `master`, `asset`）

**クライアント側での対応:**

クライアント（iOS/Android/Web）で必要に応じてキャメルケースに変換してください。

```typescript
// TypeScript例: スネークケースをキャメルケースに変換
function snakeToCamel(obj: any): any {
  if (Array.isArray(obj)) {
    return obj.map(v => snakeToCamel(v));
  } else if (obj !== null && obj.constructor === Object) {
    return Object.keys(obj).reduce((result, key) => {
      const camelKey = key.replace(/_([a-z])/g, (g) => g[1].toUpperCase());
      result[camelKey] = snakeToCamel(obj[key]);
      return result;
    }, {} as any);
  }
  return obj;
}

// 使用例
const response = await apiClient.login();
const data = snakeToCamel(response.data);
// { sysPlayer, trxUnitList, loginBonusList }
```

```kotlin
// Kotlin例: スネークケースをキャメルケースに変換
data class LoginResponse(
    @SerializedName("sys_player") val sysPlayer: Player,
    @SerializedName("trx_unit_list") val trxUnitList: List<Unit>,
    @SerializedName("trx_item_list") val trxItemList: List<Item>,
    @SerializedName("login_bonus_list") val loginBonusList: List<Bonus>
)
```

**禁止事項:**

```php
// ❌ Bad: サーバー側でキャメルケースに変換しない
public function toArray(): array
{
    return [
        'myId' => $this->myId,          // NG
        'trxUnits' => $this->trxUnits,  // NG
    ];
}

// ❌ Bad: 複数形を使用しない
public function toArray(): array
{
    return [
        'trx_units' => [...],           // NG: units
        'trx_wallets' => [...],         // NG: wallets
    ];
}

// ✅ Good: スネークケース + _list サフィックス
public function toArray(): array
{
    return [
        'my_id' => $this->myId,
        'trx_unit_list' => array_map(
            fn($unit) => $unit->toResponseArray(),
            $this->trxUnits
        ),
        'trx_wallet_list' => array_map(
            fn($wallet) => $wallet->toResponseArray(),
            $this->trxWallets
        ),
    ];
}
```

#### 成功レスポンス

**基本構造:**

```json
{
  "needs_update": true,
  "latest_deploy_id": 1,
  "latest_deploy_key": 1,
  "master": {
    "deploy_master_id": 3,
    "hash": "430fe9e35ab4660c35127cb6d7425aaf9c2b4d3d1868a5845d1a96d9409a1736"
  }
}
```

**命名規約:**
- **必須**: スネークケース（`needs_update`, `latest_deploy_id`）
- **禁止**: キャメルケース（`needsUpdate`, `latestDeployId`）
- **推奨**: 短く明確な名前
- **ネストオブジェクト**: 最大3階層まで

**データ構造:**
- null値は省略可能（クライアントの判定を簡潔に）
- ネストは最大3階層まで（深すぎる階層は可読性を下げる）
- 配列は空配列`[]`で返す（nullではない）

**例: プレイヤー情報レスポンス**

```json
{
  "player": {
    "id": 12345,
    "name": "Player Name",
    "level": 50,
    "exp": 123456
  },
  "items": [
    {
      "id": 1,
      "name": "Potion",
      "quantity": 10
    }
  ],
  "equipments": []
}
```

#### エラーレスポンス

**基本構造:**

```json
{
  "error": "Invalid action",
  "message": "The requested action is not supported",
  "code": "INVALID_ACTION"
}
```

**フィールド:**
- `error`: 短いエラー概要（英語）
- `message`: 詳細なエラーメッセージ（ユーザー向け、日本語可）
- `code`: エラーコード（大文字スネークケース）

**エラーコードの例:**

| コード | 説明 | HTTPステータス |
|--------|------|----------------|
| `INVALID_ACTION` | 無効なアクション | 400 |
| `UNAUTHORIZED` | 認証エラー | 401 |
| `FORBIDDEN` | 権限不足 | 403 |
| `NOT_FOUND` | リソースが見つからない | 404 |
| `VALIDATION_ERROR` | バリデーションエラー | 422 |
| `SERVER_ERROR` | サーバー内部エラー | 500 |
| `MAINTENANCE` | メンテナンス中 | 503 |

**バリデーションエラーの詳細:**

```json
{
  "error": "Validation failed",
  "message": "The given data was invalid",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

#### レスポンスの例

**成功レスポンス（VersionResponse）**

```json
{
  "needs_update": false,
  "latest_deploy_id": 5,
  "latest_deploy_key": 100,
  "master": {
    "deploy_master_id": 10,
    "hash": "430fe9e35ab4660c35127cb6d7425aaf9c2b4d3d1868a5845d1a96d9409a1736"
  },
  "asset": {
    "deploy_asset_id": 8,
    "hash": "7f9a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p7q8r9s0t1u2v3w4x5y6z7a8b9c0d1e2"
  }
}
```

**エラーレスポンス（認証失敗）**

```json
{
  "error": "Authentication failed",
  "message": "Invalid credentials",
  "code": "UNAUTHORIZED"
}
```

#### HTTPステータスコードの使用

| ステータス | 用途 | 例 |
|-----------|------|---|
| 200 | 成功 | データ取得、更新成功 |
| 201 | 作成成功 | リソース作成成功 |
| 400 | リクエストエラー | 無効なパラメータ |
| 401 | 認証エラー | トークン無効 |
| 403 | 権限エラー | アクセス拒否 |
| 404 | 存在しない | リソースが見つからない |
| 422 | バリデーションエラー | 入力データ不正 |
| 500 | サーバーエラー | 予期しないエラー |
| 503 | サービス停止 | メンテナンス中 |

#### レスポンス作成のベストプラクティス

1. **Responseクラスの使用**
   ```php
   // ✅ Good: 専用のResponseクラス
   return new VersionResponse([
       'needs_update' => $needsUpdate,
       'latest_deploy_id' => $deployId,
   ]);
   ```

2. **一貫性のある構造**
   - 同じリソースは常に同じ構造で返す
   - オプショナルなフィールドは明確に定義

3. **適切なHTTPステータス**
   - ステータスコードとレスポンス内容を一致させる
   - エラー時は必ずエラーレスポンス構造を使用

---

## マスターデータ配信システム

このプロジェクトでは、ゲームのマスターデータ（キャラクター定義、アイテム定義等）をクライアントに効率的に配信するため、**SQLiteファイル配信**方式を採用しています。

### アーキテクチャ方針

**配信形式: SQLiteファイル一括配信**

```
運営ツール (tol)                API サーバー           クライアント
┌─────────────────────┐        ┌──────────────┐        ┌────────────────┐
│ Google Spreadsheet  │        │              │        │                │
│   ↓ インポート      │        │ POST         │        │ POST           │
│ mstデータベース     │        │ /auth/version│←───────│ /auth/version  │
│   ↓ エクスポート    │        │              │        │ (deploy_key)   │
│ SQLiteファイル生成  │        │ needs_update │───────→│                │
│ SHA-256ハッシュ付与 │        │ + hash       │        │ needs_update?  │
│   ↓ 登録           │        └──────────────┘        │   ↓ YES        │
│ sys_deploy_master   │                                 │ GET /masterdata│
│ sys_deploy          │───────────────────────────────→│ /{hash}.sqlite │
└─────────────────────┘                                 └────────────────┘
   tool/public/masterdata/
   master_{hash}.sqlite
```

#### 設計理由

1. **SQLite一括配信**
   - クライアント側でそのままSQLクエリが使える
   - スキーマ変更に強い（差分管理不要）
   - ファイル単体でバージョン管理できる

2. **SHA-256ハッシュによるバージョン管理**
   - ファイル名にハッシュを含める: `master_{hash}.sqlite`
   - 同一内容なら同じファイルを再利用（重複生成防止）
   - クライアントはハッシュ比較で更新要否を判定

3. **sys_deployとの連携**
   - `sys_deploy_master.hash` が最新SQLiteのハッシュ
   - `auth/version` APIがクライアントのハッシュと比較
   - `needs_update: true` のときDLパスをレスポンスに含める

### マスターデータ生成フロー（運営ツール）

#### 1. スプレッドシートからインポート

```
tol の「マスターインポート」画面
    → Google Drive フォルダのスプレッドシートを選択
    → mstデータベースに TRUNCATE & INSERT
    → 「SQLiteエクスポート & デプロイ登録」ボタンをクリック
```

#### 2. SQLiteエクスポート（MasterDataExporter）

```php
// tool/app/Services/MasterDataExporter.php
// mst_* テーブルを全件取得 → SQLiteに変換
// SHA-256ハッシュを計算 → master_{hash}.sqlite として配置

// 出力先: tool/public/masterdata/master_{hash}.sqlite
// DLパス: GET /masterdata/master_{hash}.sqlite
```

- mst_* テーブル全件を SQLite に変換（PDO使用）
- MySQLの型をSQLiteの INTEGER / REAL / TEXT に変換
- 複合主キー（`__l10n` テーブル等）にも対応
- 同一ハッシュのファイルが既存の場合は再利用（冪等性）

#### 3. sys_deploy登録（MasterDeployService）

```php
// tool/app/Services/MasterDeployService.php
// sys_deploy_master に hash / deploy_key を登録
// sys_deploy の is_active を切り替え（旧: false → 新: true）

// deploy_key の形式: YYYYMMDD * 100 + 当日連番
// 例: 2026090501（2026年9月5日1回目）
```

- 同一ハッシュの重複登録は防止（`is_new: false` で返す）
- `sys_deploy_master` → `sys_deploy_asset`（ダミー）→ `sys_deploy` の順でINSERT
- 登録後、旧 `sys_deploy` の `is_active` を全て `false` にしてから新レコードを `true`

### デプロイ管理テーブル

マスターデータの配信を管理するため、以下のテーブル構造を使用します：

#### sys_deploy（配信管理テーブル）

```sql
CREATE TABLE sys_deploy (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deploy_key INT UNSIGNED UNIQUE NOT NULL COMMENT '人間が管理しやすいキー（YYYYMMDD連番）',
    start_at DATETIME NOT NULL COMMENT 'ダウンロード可能日時',
    sys_deploy_master_id BIGINT UNSIGNED NOT NULL COMMENT 'マスターデータのデプロイID',
    sys_deploy_asset_id BIGINT UNSIGNED NOT NULL COMMENT 'アセットデータのデプロイID',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'アクティブフラグ（最新1件のみtrue）',
    created_at DATETIME,
    updated_at DATETIME
);
```

**役割:**
- `is_active = true` のレコードが現在クライアントに配信中のバージョン
- `deploy_key`: YYYYMMDD * 100 + 当日連番（例: 2026090501）
- `start_at`: 配信開始日時

#### sys_deploy_master（マスターデータバージョン）

```sql
CREATE TABLE sys_deploy_master (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deploy_key INT UNSIGNED UNIQUE NOT NULL,
    hash VARCHAR(64) NOT NULL COMMENT 'SQLiteファイルのSHA-256ハッシュ',
    deploy_date DATE NOT NULL,
    deploy_count TINYINT UNSIGNED NOT NULL COMMENT 'その日の何回目のデプロイか',
    status ENUM('scheduled','in_progress','completed','failed','rolled_back'),
    deployed_by VARCHAR(191) COMMENT 'デプロイ実行者（master_import固定）',
    deployed_at DATETIME,
    description TEXT,
    created_at DATETIME,
    updated_at DATETIME
);
```

**役割:**
- `hash`: SQLiteファイル（`master_{hash}.sqlite`）のSHA-256
- この `hash` が `auth/version` レスポンスの `master.hash` として返る

### バージョンチェックフロー

#### 1. クライアントのバージョンチェック

```
POST /auth/version
{ "deploy_version": <現在保持しているsys_deploy.id> }

↓

VersionResponse:
{
  "needs_update": true,          // ハッシュが異なる場合
  "sys_deploy_id": 5,
  "latest_deploy_key": 2026090501,
  "master": {
    "sys_deploy_master_id": 4,
    "hash": "4c693973c4a84d..."   // SQLiteファイルのハッシュ
  }
}
```

#### 2. クライアントのSQLiteダウンロード

```
// needs_update: true の場合
GET /masterdata/master_4c693973c4a84d...sqlite
→ SQLiteファイルをDL
→ ローカルDBを更新
→ 以降のマスタ参照はローカルSQLiteから
```

### ファイル配置

```
tool/public/masterdata/
├── .gitkeep                              ← ディレクトリ管理用（Gitに含む）
└── {manifest_hash}/                      ← デプロイ単位のディレクトリ
    ├── mst_unit_{hash}.sqlite
    └── mst_unit__l10nも同居               ← 親テーブルと同じファイルに格納
```

- DL URL: `GET /masterdata/{manifest_hash}/mst_unit_{hash}.sqlite`
- `.gitignore` で `*.sqlite` は除外（本番はS3等に移行予定）

### チェックリスト

マスターデータをリリースする際は以下を実行：

- [ ] 運営ツールでスプレッドシートからインポート実行
- [ ] インポート結果が全て ✅ であることを確認
- [ ] 「SQLiteエクスポート & デプロイ登録」ボタンをクリック
- [ ] `deploy_key` が発行されたことを確認
- [ ] `/masterdata/{manifest_hash}/mst_unit_{hash}.sqlite` へのアクセスでDLできることを確認
- [ ] `POST /auth/version` で `needs_update: true` が返ることを確認
