# トランザクションDBマイグレーションガイド

## 概要

このディレクトリ (`database/migrations/trx/`) には、トランザクションデータベースのシャード用マイグレーションファイルが含まれています。

## 重要な注意事項

### ❌ やってはいけないこと

マイグレーションファイル内で`foreach`ループを使って複数の接続に対してテーブルを作成しないでください。

```php
// ❌ 間違った例
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

### ✅ 正しい方法

通常のLaravelマイグレーションと同じように記述してください。`migrate:shards`コマンドが自動的に各シャードに対して実行します。

```php
// ✅ 正しい例
public function up(): void
{
    Schema::create('table_name', function (Blueprint $table) {
        // ...
    });
}

public function down(): void
{
    Schema::dropIfExists('table_name');
}
```

## マイグレーション実行方法

### すべてのシャードでマイグレーション実行

```bash
php artisan migrate:shards
```

### すべてのシャードでロールバック

```bash
php artisan migrate:shards-rollback
```

### すべてのシャードのマイグレーションステータス確認

```bash
php artisan migrate:shards-status
```

### すべてのテーブルを削除して再作成（開発環境のみ）

```bash
php artisan migrate:shards --fresh --force
```

## 新しいシャードの追加方法

1. **シャーディング設定を更新** (`config/sharding.php`)
   ```php
   'nodes' => [
       1 => 'trx1',
       2 => 'trx2',
       3 => 'trx3',  // 新しいシャードを追加
   ],
   ```

2. **データベース接続設定を追加** (`config/database.php`)
   ```php
   'trx3' => [
       'driver' => 'mysql',
       'host' => env('DB_TRX3_HOST', 'db-trx3'),
       'port' => env('DB_TRX3_PORT', '3306'),
       'database' => env('DB_TRX3_DATABASE') ?: env('APP_NAME', 'laravel') . '-' . env('APP_ENV', 'local') . '-trx3',
       'username' => env('DB_TRX3_USERNAME', 'root'),
       'password' => env('DB_TRX3_PASSWORD', 'root'),
       // ...
   ],
   ```

3. **環境変数を設定** (`.env`)
   ```env
   DB_TRX3_HOST=db-trx3
   DB_TRX3_PORT=3306
   DB_TRX3_DATABASE=arche-local-trx3
   DB_TRX3_USERNAME=root
   DB_TRX3_PASSWORD=root
   ```

4. **Docker Composeを更新** (`docker-compose.yml`)（必要に応じて）
   ```yaml
   db-trx3:
     image: mysql:latest
     container_name: db-trx3
     environment:
       MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
     ports:
       - "33067:3306"
     volumes:
       - db_trx3_data:/var/lib/mysql
   ```

5. **マイグレーション実行**
   ```bash
   php artisan migrate:shards
   ```

これで、新しいシャード`trx3`が自動的にマイグレーション対象に含まれます。

## シャーディング設定

シャード設定は `config/sharding.php` で管理されています。このファイルを編集することで、シャードの追加・削除が可能です。
