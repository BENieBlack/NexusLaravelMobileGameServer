# シャーディング対応マイグレーションシステム

## 概要

トランザクションデータベース（`trx1`, `trx2`, ...）のシャーディングに対応したマイグレーションシステムです。設定ファイルでシャードノードを管理し、新しいシャードを追加すると自動的にマイグレーション対象に含まれます。

## アーキテクチャ

### 1. シャーディング設定 (`config/sharding.php`)

すべてのシャードノードを一元管理する設定ファイルです。

```php
<?php

return [
    'transaction' => [
        'prefix' => 'trx',
        
        'nodes' => [
            1 => 'trx1',
            2 => 'trx2',
            // 3 => 'trx3',  // 新しいシャードを追加する場合
        ],
    ],
];
```

### 2. カスタムArtisanコマンド

#### `migrate:shards`
すべてのトランザクションシャードに対してマイグレーションを実行します。

**使用例:**
```bash
# 通常のマイグレーション実行
php artisan migrate:shards

# 本番環境で実行（確認なし）
php artisan migrate:shards --force

# すべてのテーブルを削除して再作成（開発環境のみ）
php artisan migrate:shards --fresh --force

# 特定のパスのマイグレーションのみ実行
php artisan migrate:shards --path=database/migrations/trx/custom

# Dry run（実際には実行せず、SQLのみ表示）
php artisan migrate:shards --pretend
```

**動作:**
1. `config/sharding.php`からシャード接続名のリストを取得
2. 各シャードに対して順番に`migrate`コマンドを実行
3. デフォルトで`database/migrations/trx`ディレクトリのマイグレーションを使用
4. エラーが発生したシャードをログに記録し、他のシャードは継続

#### `migrate:shards-status`
すべてのシャードのマイグレーション状態を確認します。

**使用例:**
```bash
# すべてのシャードのステータスを表示
php artisan migrate:shards-status

# 特定のシャードのみ表示
php artisan migrate:shards-status --database=trx1

# 未実行のマイグレーションのみ表示
php artisan migrate:shards-status --pending
```

#### `migrate:shards-rollback`
すべてのシャードで最後のマイグレーションをロールバックします。

**使用例:**
```bash
# 最後のバッチをロールバック
php artisan migrate:shards-rollback

# 最後の3ステップをロールバック
php artisan migrate:shards-rollback --step=3

# 本番環境で実行（確認なし）
php artisan migrate:shards-rollback --force
```

### 3. マイグレーションファイルの書き方

#### ✅ 正しい書き方

通常のLaravelマイグレーションと同じように記述します。コマンドが自動的に各シャードに対して実行します。

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_example', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_example');
    }
};
```

#### ❌ 間違った書き方（古い方式）

マイグレーションファイル内で`foreach`ループを使って複数の接続を処理しないでください。

```php
// ❌ これは動作しません
protected $connections = ['trx1', 'trx2'];

public function up(): void
{
    foreach ($this->connections as $connection) {
        Schema::connection($connection)->create('table_name', function (Blueprint $table) {
            // ...
        });
    }
}
```

**理由:**
- `migrate:shards`コマンドが各シャードに対して個別にマイグレーションを実行します
- マイグレーションファイル内でループすると、すべてのシャードに対して二重に実行されます
- 結果として「テーブルが既に存在する」エラーが発生します

### 4. シーダーの動的対応

`TrxPlayerSeeder`などのシーダーも設定ファイルからシャード接続を取得するように更新されています。

```php
class TrxPlayerSeeder extends Seeder
{
    protected function getConnections(): array
    {
        return array_values(config('sharding.transaction.nodes', []));
    }

    public function run(): void
    {
        $connections = $this->getConnections();
        // ...
    }
}
```

## 新しいシャードの追加手順

### 1. シャーディング設定を更新

`config/sharding.php`にノードを追加:

```php
'nodes' => [
    1 => 'trx1',
    2 => 'trx2',
    3 => 'trx3',  // 新しく追加
    4 => 'trx4',  // 新しく追加
],
```

### 2. データベース接続設定を追加

`config/database.php`に接続設定を追加:

```php
'connections' => [
    // ... 既存の設定 ...
    
    'trx3' => [
        'driver' => 'mysql',
        'host' => env('DB_TRX3_HOST', 'db-trx3'),
        'port' => env('DB_TRX3_PORT', '3306'),
        'database' => env('DB_TRX3_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-trx3',
        'username' => env('DB_TRX3_USERNAME', 'root'),
        'password' => env('DB_TRX3_PASSWORD', 'root'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
    
    'trx4' => [
        'driver' => 'mysql',
        'host' => env('DB_TRX4_HOST', 'db-trx4'),
        'port' => env('DB_TRX4_PORT', '3306'),
        'database' => env('DB_TRX4_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-trx4',
        'username' => env('DB_TRX4_USERNAME', 'root'),
        'password' => env('DB_TRX4_PASSWORD', 'root'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

### 3. 環境変数を設定

`.env`ファイルに追加:

```env
# Transaction Database - Node 3
DB_TRX3_HOST=db-trx3
DB_TRX3_PORT=3306
DB_TRX3_DATABASE=arche-local-trx3
DB_TRX3_USERNAME=root
DB_TRX3_PASSWORD=root

# Transaction Database - Node 4
DB_TRX4_HOST=db-trx4
DB_TRX4_PORT=3306
DB_TRX4_DATABASE=arche-local-trx4
DB_TRX4_USERNAME=root
DB_TRX4_PASSWORD=root
```

### 4. Docker Composeを更新（ローカル開発環境の場合）

`docker-compose.yml`にデータベースコンテナを追加:

```yaml
services:
  # ... 既存のサービス ...
  
  db-trx3:
    image: mysql:latest
    container_name: db-trx3
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    ports:
      - "33067:3306"
    volumes:
      - db_trx3_data:/var/lib/mysql
    networks:
      - local-network

  db-trx4:
    image: mysql:latest
    container_name: db-trx4
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    ports:
      - "33068:3306"
    volumes:
      - db_trx4_data:/var/lib/mysql
    networks:
      - local-network

volumes:
  # ... 既存のボリューム ...
  db_trx3_data:
    driver: local
  db_trx4_data:
    driver: local
```

### 5. コンテナを起動

```bash
docker compose up -d db-trx3 db-trx4
```

### 6. マイグレーション実行

```bash
php artisan migrate:shards
```

これで、新しいシャード（`trx3`, `trx4`）が自動的にマイグレーション対象に含まれます。

## システムデータベース連携

シャード割り当てはシステムデータベース（`sys`）で管理されます。

### `sys_sharding_node`テーブル
シャードノードの定義

| カラム | 型 | 説明 |
|--------|-----|------|
| id | bigint | ノードID |
| node_no | int | ノード番号（1, 2, 3...） |
| connection_name | varchar | 接続名（trx1, trx2...） |

### `sys_sharding_node_player`テーブル
プレイヤーとシャードの割り当て

| カラム | 型 | 説明 |
|--------|-----|------|
| sys_player_id | bigint | プレイヤーID |
| sys_sharding_node_id | bigint | 割り当てられたノードID |

## ディレクトリ構造

```
api/
├── app/Console/Commands/
│   ├── MigrateShards.php              # メインマイグレーションコマンド
│   ├── MigrateShardsStatus.php        # ステータス確認コマンド
│   └── MigrateShardsRollback.php      # ロールバックコマンド
├── config/
│   ├── sharding.php                   # シャーディング設定
│   └── database.php                   # データベース接続設定
└── database/
    ├── migrations/
    │   ├── trx/                       # トランザクションDB用マイグレーション
    │   │   ├── README.md              # マイグレーション作成ガイド
    │   │   ├── 2026_01_01_000001_create_game_tables.php
    │   │   └── ...
    │   ├── sys/                       # システムDB用マイグレーション
    │   ├── mst/                       # マスターDB用マイグレーション
    │   └── log/                       # ログDB用マイグレーション
    └── seeders/
        └── TrxPlayerSeeder.php        # 動的シャード対応シーダー
```

## ベストプラクティス

### マイグレーション作成時

1. **適切なディレクトリに配置**
   - トランザクションDB用: `database/migrations/trx/`
   - システムDB用: `database/migrations/sys/`
   - マスターDB用: `database/migrations/mst/`

2. **通常のLaravel形式で記述**
   - `Schema::create()` を直接使用
   - `Schema::connection()` は使用しない（コマンドが自動設定）

3. **命名規則を守る**
   - 日付_時刻_処理内容.php
   - 例: `2026_07_14_123456_create_trx_example_table.php`

### 本番環境デプロイ時

1. **事前確認**
   ```bash
   # Pending状態のマイグレーションを確認
   php artisan migrate:shards-status --pending
   ```

2. **Dry runで確認**
   ```bash
   # 実行されるSQLを確認（実際には実行しない）
   php artisan migrate:shards --pretend
   ```

3. **本番実行**
   ```bash
   # 本番環境で実行
   php artisan migrate:shards --force
   ```

4. **実行後確認**
   ```bash
   # すべてのシャードでマイグレーションが完了したか確認
   php artisan migrate:shards-status
   ```

### ロールバック時

```bash
# 最後のバッチをロールバック
php artisan migrate:shards-rollback --force

# ステータス確認
php artisan migrate:shards-status
```

## トラブルシューティング

### エラー: "Migration table not found"

**原因:** マイグレーションテーブルが作成されていません。

**解決策:**
```bash
php artisan migrate:install --database=trx1
php artisan migrate:install --database=trx2
# または
php artisan migrate:shards
```

### エラー: "Table already exists"

**原因:** マイグレーションファイル内で`foreach`ループを使用している可能性があります。

**解決策:** マイグレーションファイルを通常のLaravel形式に修正してください。

### 特定のシャードでのみエラーが発生

**確認方法:**
```bash
# 特定のシャードのステータス確認
php artisan migrate:status --database=trx1

# 特定のシャードで直接マイグレーション実行
php artisan migrate --database=trx1 --path=database/migrations/trx
```

## まとめ

このシステムにより、以下が実現されます:

- ✅ 設定ベースのシャード管理（コードの変更不要）
- ✅ 新しいシャード追加時の自動対応
- ✅ すべてのシャードに対する一括マイグレーション
- ✅ 個別シャードのステータス確認
- ✅ エラーハンドリングと詳細なログ出力

新しいシャードを追加する際は、設定ファイル（`config/sharding.php`）を更新するだけで、既存のマイグレーションやシーダーが自動的に対応します。
