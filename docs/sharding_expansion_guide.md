# シャード増設手順ガイド

## 概要

本システムは完全動的シャーディングに対応しており、環境変数`DB_SHARD_COUNT`を変更するだけで簡単にシャード数を増減できます。このドキュメントでは、シャードを増設する具体的な手順を説明します。

## 前提条件

- Dockerとdocker-composeがインストール済み
- 既存のシャード（trx1-N, log1-N）が正常に動作している
- データベースマイグレーションの基本的な理解

## シャード増設の流れ

シャード数を増やす場合（例：2シャード → 3シャード）は、以下の4ステップで実施します：

### ステップ1: 環境変数の設定

#### 1.1 ルート.envファイルの更新

`/.env`を編集し、`DB_SHARD_COUNT`の値を変更します。

```bash
# Sharding Configuration
DB_SHARD_COUNT=3  # 2 → 3に変更
```

#### 1.2 シャード接続情報の追加

同じルートの `.env` に、新しいシャードの接続情報を追加します。
（`api/.env` は読み込まれないため置かないこと。`api/bootstrap/app.php` が
リポジトリルートの `.env` を参照する）

```bash
# トランザクションDB接続 - ノード3（新規追加）
DB_TRX3_HOST=db-trx3
DB_TRX3_PORT=3306
DB_TRX3_DATABASE=nexus-local-trx3
DB_TRX3_USERNAME=root
DB_TRX3_PASSWORD=root

# シャーディング設定
DB_SHARD_COUNT=3

# ログDB接続 - ノード3（新規追加）
DB_LOG3_HOST=db-log3
DB_LOG3_PORT=3306
DB_LOG3_DATABASE=nexus-local-log3
DB_LOG3_USERNAME=root
DB_LOG3_PASSWORD=root
```

**命名規則:**
- TrxDB: `DB_TRX{N}_*` (N=1,2,3,...)
- LogDB: `DB_LOG{N}_*` (N=1,2,3,...)

### ステップ2: Docker環境の構築

#### 2.1 docker-compose.ymlの編集

新しいシャード用のコンテナ定義を追加します。

```yaml
  # TrxDB3（新規追加）
  db-trx3:
    image: mysql:latest
    container_name: db-trx3
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    ports:
      - "63163:3306"  # ホストポート（他と重複しないように）
    volumes:
      - db_trx3_data:/var/lib/mysql
    networks:
      - local-network

  # LogDB3（新規追加）
  db-log3:
    image: mysql:latest
    container_name: db-log3
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    ports:
      - "63263:3306"  # ホストポート（他と重複しないように）
    volumes:
      - db_log3_data:/var/lib/mysql
    networks:
      - local-network
```

#### 2.2 ボリューム定義の追加

同じく`docker-compose.yml`のvolumesセクションに追加：

```yaml
volumes:
  db_mst_data:
  db_trx1_data:
  db_trx2_data:
  db_trx3_data:    # 新規追加
  db_log1_data:
  db_log2_data:
  db_log3_data:    # 新規追加
  db_admin_data:
  db_sys_data:
  db_tool_data:
    driver: local
```

#### 2.3 api-phpコンテナの環境変数設定（初回のみ）

`docker-compose.yml`のapi-phpサービスに環境変数を追加（まだ設定していない場合）：

```yaml
  api-php:
    # ... 他の設定 ...
    environment:
      - CLIENT_SECRET=${CLIENT_SECRET:-your-secret-key-change-in-production-12345}
      - DB_SHARD_COUNT=${DB_SHARD_COUNT:-2}  # この行を追加
```

#### 2.4 ポート番号の参考

既存のポート割り当て例：
- db-mst: 63063
- db-log1: 63261
- db-trx1: 63161
- db-trx2: 63162
- db-log2: 63262
- **db-log3: 63263（新規）**
- **db-trx3: 63163（新規）**

### ステップ3: Dockerコンテナの起動

#### 3.1 新規コンテナの起動

```bash
docker compose up -d db-trx3 db-log3
```

起動確認：
```bash
docker compose ps | grep -E "(db-trx|db-log)"
```

期待される出力例：
```
db-log1    mysql:latest    ...    Up    0.0.0.0:63261->3306/tcp
db-log2    mysql:latest    ...    Up    0.0.0.0:63262->3306/tcp
db-log3    mysql:latest    ...    Up    0.0.0.0:63263->3306/tcp
db-trx1    mysql:latest    ...    Up    0.0.0.0:63161->3306/tcp
db-trx2    mysql:latest    ...    Up    0.0.0.0:63162->3306/tcp
db-trx3    mysql:latest    ...    Up    0.0.0.0:63163->3306/tcp
```

#### 3.2 データベースの作成

MySQLコンテナ起動後、データベースを作成します（初回起動時は10秒程度待機）：

```bash
# TrxDB3の作成
docker exec db-trx3 mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS \`nexus-local-trx3\`;"

# LogDB3の作成
docker exec db-log3 mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS \`nexus-local-log3\`;"
```

#### 3.3 テスト用データベースの作成

テストを実行する場合は、テスト用データベースも作成します：

```bash
# TrxDB3テスト用データベースの作成
docker exec db-trx3 mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS \`nexus-testing-trx3\`;"

# LogDB3テスト用データベースの作成
docker exec db-log3 mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS \`nexus-testing-log3\`;"
```

#### 3.4 phpunit.xmlの更新

**重要:** テストを実行する場合は、`api/phpunit.xml`に新しいシャードのテスト環境変数を追加する必要があります。

`api/phpunit.xml`のphpセクションに以下を追加：

```xml
<!-- Transaction DB Testing - Node 3 -->
<env name="DB_TRX3_HOST" value="db-trx3"/>
<env name="DB_TRX3_PORT" value="3306"/>
<env name="DB_TRX3_DATABASE" value="nexus-testing-trx3"/>
<env name="DB_TRX3_USERNAME" value="root"/>
<env name="DB_TRX3_PASSWORD" value="root"/>

<!-- Log DB Testing - Node 3 -->
<env name="DB_LOG3_HOST" value="db-log3"/>
<env name="DB_LOG3_PORT" value="3306"/>
<env name="DB_LOG3_DATABASE" value="nexus-testing-log3"/>
<env name="DB_LOG3_USERNAME" value="root"/>
<env name="DB_LOG3_PASSWORD" value="root"/>
```

**注意:** この設定を追加しないと、テスト実行時に "Unknown database" エラーが発生します。

#### 3.5 api-phpコンテナの再起動

環境変数の変更を反映させるため、api-phpコンテナを再起動します：

```bash
docker compose up -d api-php
```

または：

```bash
docker compose restart api-php
```

#### 3.6 環境変数の確認

正しく設定されているか確認：

```bash
docker exec api-php php artisan tinker --execute="
echo 'DB_SHARD_COUNT: ' . getenv('DB_SHARD_COUNT') . PHP_EOL;
"
```

期待される出力：
```
DB_SHARD_COUNT: 3
```

### ステップ4: マイグレーション実行

#### 4.1 TrxDBマイグレーション

新しいシャードにTrxDBテーブルを作成します：

```bash
docker exec api-php php artisan trx:migrate --force
```

期待される出力：
```
Running TrxDB migrations on all 3 shards...
Target shards: trx1, trx2, trx3
...
📦 Migrating TrxDB: trx1
   INFO  Nothing to migrate.
✅ Migration completed for trx1

📦 Migrating TrxDB: trx2
   INFO  Nothing to migrate.
✅ Migration completed for trx2

📦 Migrating TrxDB: trx3
   INFO  Preparing database.
   Creating migration table ...
   INFO  Running migrations.
   2026_01_02_000001_create_billing_tables ... DONE
   2026_01_02_000001_create_player_tables ... DONE
   ...
✅ Migration completed for trx3

🎉 All TrxDB migrations completed successfully!
```

#### 4.2 LogDBマイグレーション

新しいシャードにLogDBテーブルを作成します：

```bash
docker exec api-php php artisan pitr:migrate --force
```

期待される出力：
```
Running LogDB migrations on all 3 shards...
Target shards: log1, log2, log3
...
📦 Migrating LogDB: log3
   INFO  Preparing database.
   Creating migration table ...
   INFO  Running migrations.
   2026_01_01_000001_create_billing_log_tables ... DONE
   2026_01_01_000001_create_gacha_log_tables ... DONE
   ...
✅ Migration completed for log3

🎉 All LogDB migrations completed successfully!
```

### ステップ5: 動作確認

#### 5.1 テーブル作成確認

各シャードにテーブルが正しく作成されているか確認：

```bash
# TrxDB3のテーブル数確認
docker exec db-trx3 mysql -uroot -proot nexus-local-trx3 -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema='nexus-local-trx3';"

# LogDB3のテーブル数確認
docker exec db-log3 mysql -uroot -proot nexus-local-log3 -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema='nexus-local-log3';"
```

期待される値：
- TrxDB3: 17テーブル（migrations + 16トランザクションテーブル）
- LogDB3: 20テーブル（migrations + 19ログテーブル）

#### 5.2 テーブル一覧確認

```bash
# TrxDB3のテーブル一覧
docker exec db-trx3 mysql -uroot -proot nexus-local-trx3 -e "SHOW TABLES;"

# LogDB3のテーブル一覧
docker exec db-log3 mysql -uroot -proot nexus-local-log3 -e "SHOW TABLES;"
```

#### 5.3 ShardMapperの動作確認

シャードマッピングが正しく機能しているか確認：

```bash
docker exec api-php php artisan tinker --execute="
use NexusPitr\Logger\ShardMapper;
echo 'TRX Connections: ';
print_r(ShardMapper::getAllTrxConnections());
echo PHP_EOL . 'LOG Connections: ';
print_r(ShardMapper::getAllLogConnections());
echo PHP_EOL . 'Mapping: trx3 -> ' . ShardMapper::getLogConnection('trx3') . PHP_EOL;
echo 'Mapping: log3 -> ' . ShardMapper::getTrxConnection('log3') . PHP_EOL;
"
```

期待される出力：
```
TRX Connections: Array
(
    [0] => trx1
    [1] => trx2
    [2] => trx3
)

LOG Connections: Array
(
    [0] => log1
    [1] => log2
    [2] => log3
)

Mapping: trx3 -> log3
Mapping: log3 -> trx3
```

#### 5.4 全シャード一括確認スクリプト

```bash
echo "=== Dynamic Sharding Verification ==="
echo ""
echo "TrxDB Shards:"
for i in {1..3}; do
  count=$(docker exec db-trx$i mysql -uroot -proot nexus-local-trx$i -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema='nexus-local-trx$i';" 2>&1 | grep -v "Using a password" | tail -1)
  echo "  trx$i: $count tables"
done

echo ""
echo "LogDB Shards:"
for i in {1..3}; do
  count=$(docker exec db-log$i mysql -uroot -proot nexus-local-log$i -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema='nexus-local-log$i';" 2>&1 | grep -v "Using a password" | tail -1)
  echo "  log$i: $count tables"
done
```

## トラブルシューティング

### エラー: "Unknown connection: trxN"

**原因:** 環境変数が正しく読み込まれていない、または`DB_TRXN_*`の設定が不足している。

**解決策:**
1. ルートの `.env` の設定を確認（`api/.env` は読み込まれない）
2. api-phpコンテナを再起動: `docker compose restart api-php`
3. 環境変数を確認: `docker exec api-php env | grep DB_TRX`

### エラー: "SQLSTATE[HY000] [2002] Connection refused"

**原因:** MySQLコンテナが起動していない、または準備が完了していない。

**解決策:**
1. コンテナの状態確認: `docker compose ps`
2. コンテナログ確認: `docker compose logs db-trx3`
3. MySQLの準備完了を待つ（初回起動時は10-20秒程度）

### マイグレーションエラー

**原因:** マイグレーションファイルの構文エラーやパス設定の問題。

**解決策:**
1. エラーメッセージを確認
2. 既存シャード（trx1, log1）でもマイグレーションが正常に動作するか確認
3. `--force`オプションを使用しているか確認

## 本番環境での注意事項

### 1. ダウンタイムの最小化

本番環境でシャードを増設する場合：

1. **新規シャードのみを作成**（既存シャードへの影響なし）
2. **マイグレーション実行**（新規シャードのみ）
3. **アプリケーションコード更新なし**（動的シャーディングのため）
4. **負荷分散設定の更新**（新シャードへのトラフィック振り分け開始）

### 2. データ移行の考慮

既存データの再シャーディングが必要な場合：

- 別途データ移行スクリプトの作成が必要
- シャードキー（sys_player_id）に基づくデータの再配置
- ダウンタイムまたはリードレプリカの活用を検討

### 3. バックアップ

シャード増設前に必ずバックアップを取得：

```bash
# TrxDBのバックアップ例
docker exec db-trx1 mysqldump -uroot -proot nexus-local-trx1 > backup_trx1_$(date +%Y%m%d).sql
docker exec db-trx2 mysqldump -uroot -proot nexus-local-trx2 > backup_trx2_$(date +%Y%m%d).sql

# LogDBのバックアップ例
docker exec db-log1 mysqldump -uroot -proot nexus-local-log1 > backup_log1_$(date +%Y%m%d).sql
docker exec db-log2 mysqldump -uroot -proot nexus-local-log2 > backup_log2_$(date +%Y%m%d).sql
```

## シャード減少（スケールダウン）

シャード数を減らす場合は**データ移行が必須**です：

1. 削除予定シャードのデータを残存シャードに移行
2. 移行完了後、`DB_SHARD_COUNT`を減少
3. 不要なコンテナを停止・削除

**警告:** データ移行なしにシャードを削除すると、データロスが発生します。

## 参考情報

### 関連ドキュメント

- [log_db_sharding_design.md](./log_db_sharding_design.md) - LogDBシャーディング設計
- [sharding_migration_system.md](./sharding_migration_system.md) - シャーディングマイグレーションシステム
- [pitr_implementation_summary.md](./pitr_implementation_summary.md) - PITR実装サマリー

### 主要なファイル

- **ShardMapper**: `packages/nexus-pitr/src/Logger/ShardMapper.php`
- **TrxMigrateCommand**: `packages/nexus-pitr/src/Commands/TrxMigrateCommand.php`
- **PitrMigrateCommand**: `packages/nexus-pitr/src/Commands/PitrMigrateCommand.php`
- **Config**: `api/config/database.php`

### 動的シャーディングの仕組み

本システムは以下の原則に基づいて動作します：

1. **環境変数駆動**: `DB_SHARD_COUNT`でシャード数を制御
2. **1:1マッピング**: trxN ↔ logN（N=1,2,3,...）
3. **コード変更不要**: ShardMapperが自動的にシャード数を認識
4. **マイグレーション自動化**: 各シャードに対して同一のスキーマを自動展開

## まとめ

シャード増設は以下の手順で完了します：

1. ✅ **環境変数設定** (ルートの .env, docker-compose.yml)
2. ✅ **Dockerコンテナ起動** (docker compose up -d)
3. ✅ **データベース作成** (CREATE DATABASE)
4. ✅ **テスト用データベース作成** (CREATE DATABASE for testing)
5. ✅ **phpunit.xml更新** (新しいシャードのテスト環境変数を追加)
6. ✅ **マイグレーション実行** (trx:migrate, pitr:migrate)

**注意事項:**
- テストを実行する場合は、必ず`api/phpunit.xml`に新しいシャードの設定を追加してください
- テスト用データベース名は本番と異なる命名規則（`nexus-testing-*`）を使用します
- phpunit.xmlの更新を忘れると、テスト実行時にデータベース接続エラーが発生します

完全動的シャーディングにより、アプリケーションコードの変更なしに、簡単にスケールアウトが可能です。
