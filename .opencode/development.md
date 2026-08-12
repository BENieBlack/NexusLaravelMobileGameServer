
## 開発環境構築

### 環境構築の自動化

このプロジェクトでは、`./command/setup.sh`スクリプトを使用して環境構築を完全に自動化しています。

#### 初回セットアップ

```bash
./command/setup.sh
```

このコマンド1つで以下が自動実行されます：

1. **Dockerコンテナの起動**
   - 既存のコンテナとボリュームを完全に削除（クリーンインストール）
   - 全コンテナを新規起動
   
2. **データベースの初期化**
   - 全7つのデータベースを作成（sys, mst, log, trx1, trx2, adm, tol）
   - 各データベースに`migrations`テーブルを作成

3. **APIプロジェクトのセットアップ**
   - Composerパッケージのインストール
   - NPMパッケージのインストール
   - Viteビルドの実行
   - 全データベース（sys, mst, log, trx1, trx2）へのマイグレーション実行

4. **Toolプロジェクトのセットアップ**
   - Composerパッケージのインストール
   - NPMパッケージのインストール
   - Viteビルドの実行
   - 全データベース（adm, tol）へのマイグレーション実行

#### セットアップ完了後の確認

```bash
# Dockerコンテナの状態確認
docker ps

# データベース接続確認（例: sys データベース）
docker exec db-sys mysql -u root -proot nexus-local-sys -e "SHOW TABLES;"

# アプリケーションアクセス
# API: http://localhost:8090
# Tool: http://localhost:8091
```

#### トラブルシューティング

**セットアップが失敗した場合**

```bash
# コンテナとボリュームを完全に削除
docker-compose down --volumes --remove-orphans

# 再度セットアップを実行
./command/setup.sh
```

**特定のデータベースのみリセットしたい場合**

```bash
# 例: sys データベースのマイグレーションをやり直す
docker exec api-php php artisan migrate:fresh --database=sys --path=database/migrations/sys
```

### Docker構成

#### コンテナ一覧

- **api-nginx** - APIのWebサーバー（ポート: 8080）
- **api-php** - APIのPHPアプリケーション
- **tool-nginx** - 運営ツールのWebサーバー（ポート: 80）
- **tool-php** - 運営ツールのPHPアプリケーション
- **db-adm** - 管理者アカウントデータベース（ポート: 63060）
  - 管理者アカウント・権限管理
- **db-tol** - 運営ツールデータベース（ポート: 63061）
  - マスター状況、アセット、バナー、キャッシュ制御等
- **db-sys** - システム管理データベース（ポート: 63062）
  - シャーディング管理、デプロイ管理、プレイヤーマスター
- **db-mst** - ゲームマスターデータベース（ポート: 63063）
  - アイテム、キャラクター、スキルなどのマスターデータ
- **db-log** - ログデータベース（ポート: 63261）
  - APIログ、ガチャログ、課金ログなど
- **db-trx1** - トランザクションデータベース1（ポート: 63161）
  - プレイヤーのトランザクションデータ（シャード1）
- **db-trx2** - トランザクションデータベース2（ポート: 63162）
  - プレイヤーのトランザクションデータ（シャード2）
- **redis** - Redisキャッシュサーバー

#### データベース接続情報

| データベース | ホスト | ポート | データベース名 | ユーザー名 | パスワード |
|------------|--------|--------|---------------|-----------|-----------|
| admin | db-adm | 3306 (63060) | nexus-local-adm | root | root |
| tool | db-tol | 3306 (63061) | nexus-local-tol | root | root |
| sys | db-sys | 3306 (63062) | nexus-local-sys | root | root |
| mst | db-mst | 3306 (63063) | nexus-local-mst | root | root |
| log | db-log | 3306 (63261) | nexus-local-log | root | root |
| trx1 | db-trx1 | 3306 (63161) | nexus-local-trx1 | root | root |
| trx2 | db-trx2 | 3306 (63162) | nexus-local-trx2 | root | root |

※括弧内はホストマシンからアクセスする際のポート番号

## コマンドラインツール

### ./command/sail の使い方

`./command/sail`は、Laravel Sailのラッパースクリプトで、APIとAdminの両方のサービスで使用できます。

#### 基本的な使い方

```bash
# デフォルト（api-phpサービスで実行）
./command/sail artisan --version

# APIサービスを明示的に指定
./command/sail --api artisan --version

# Adminサービスを指定
./command/sail --admin artisan --version

# 環境変数で指定
SAIL_SERVICE=admin-php ./command/sail artisan route:list
```

#### よく使うコマンド

```bash
# Docker Composeコマンド
./command/sail up        # アプリケーションを起動
./command/sail up -d     # バックグラウンドで起動
./command/sail stop      # アプリケーションを停止
./command/sail restart   # アプリケーションを再起動
./command/sail ps        # コンテナの状態を表示

# Artisanコマンド（API）
./command/sail artisan migrate --database=sys --path=database/migrations/sys
./command/sail artisan migrate --database=mst --path=database/migrations/mst
./command/sail artisan db:seed
./command/sail artisan tinker

# Artisanコマンド（Tool）
./command/sail --tool artisan migrate --database=adm --path=database/migrations/adm
./command/sail --tool artisan migrate --database=tol --path=database/migrations/tol
./command/sail --tool artisan route:list

# PHPコマンド
./command/sail php -v
./command/sail --tool php artisan inspire

# Composerコマンド
./command/sail composer require package-name
./command/sail --tool composer update

# テスト実行
./command/sail test
./command/sail --tool test

# シェルアクセス
./command/sail shell           # sailユーザーでシェル起動
./command/sail root-shell      # rootユーザーでシェル起動
./command/sail --tool shell    # toolコンテナでシェル起動
```

#### マイグレーション専用コマンド

APIの全データベース（sys, mst, log, trx1, trx2）に対してマイグレーションを一括実行します：

```bash
./command/sail migrate
```

このコマンドは以下を実行します：
- `database/migrations/sys`のマイグレーションをsysデータベースに実行
- `database/migrations/mst`のマイグレーションをmstデータベースに実行
- `database/migrations/log`のマイグレーションをlogデータベースに実行
- `database/migrations/trx`のマイグレーションをtrx1およびtrx2データベースに実行

注意: このコマンドはAPIサービスでのみ使用可能です。

#### 個別データベースのマイグレーション

特定のデータベースにのみマイグレーションを実行したい場合：

```bash
# APIプロジェクト
docker exec api-php php artisan migrate --database=sys --path=database/migrations/sys
docker exec api-php php artisan migrate --database=mst --path=database/migrations/mst
docker exec api-php php artisan migrate --database=log --path=database/migrations/log
docker exec api-php php artisan migrate --database=trx1 --path=database/migrations/trx
docker exec api-php php artisan migrate --database=trx2 --path=database/migrations/trx

# Toolプロジェクト
docker exec tool-php php artisan migrate --database=adm --path=database/migrations/adm
docker exec tool-php php artisan migrate --database=tol --path=database/migrations/tol
```

**重要**: trxマイグレーションは、内部的に`$connections`配列でtrx1とtrx2の両方に対してテーブルを作成します。そのため、trx1に対して実行すれば、trx2にも同じテーブルが作成されます。

#### サービスの指定方法

1. **フラグで指定** (推奨)
   ```bash
   ./command/sail --api artisan list
   ./command/sail --tool artisan list
   ```

2. **環境変数で指定**
   ```bash
   SAIL_SERVICE=api-php ./command/sail artisan list
   SAIL_SERVICE=tool-php ./command/sail artisan list
   ```

3. **デフォルト動作**
   - サービスを指定しない場合は`api-php`がデフォルトで使用されます

## 環境構築のルール

### setup.shの役割と制約

1. **完全なクリーンインストール**
   - `setup.sh`は既存のDockerボリュームを完全に削除してから環境を構築します
   - データの保持が必要な場合は、`setup.sh`を使用せず、個別にマイグレーションを実行してください

2. **実行タイミング**
   - プロジェクトの初回セットアップ時
   - 環境を完全にリセットしたい時
   - **本番環境では絶対に実行しないでください**

3. **setup.shが行わないこと**
   - シーダーの実行（必要に応じて手動で実行してください）
   - テストデータの投入
   - 環境変数の設定（.envファイルは事前に用意してください）

### マイグレーションのルール

1. **trxマイグレーションの特殊性**
   - `api/database/migrations/trx`配下のマイグレーションは、内部的にtrx1とtrx2の両方に適用されます
   - `$connections = ['trx1', 'trx2']`配列を使用してシャード対応しています
   - **trx1に実行すれば十分**で、trx2にも自動的に反映されます

2. **Laravelデフォルトテーブル**
   - すべてのデータベースに`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`テーブルが必要です
   - これらはマイグレーションファイルで作成されます（`0001_01_01_000001_create_cache_table.php`など）
   - `users`テーブルは作成せず、各プロジェクト固有のアカウントテーブル（例: `adm_account`）を使用します

3. **マイグレーションファイルの配置**
   ```
   api/database/migrations/
   ├── sys/          # sysデータベース用
   ├── mst/          # mstデータベース用
   ├── log/          # logデータベース用
   └── trx/          # trx1, trx2データベース用（両方に適用）
   
   tool/database/migrations/
   ├── adm/          # adminデータベース用
   └── tol/          # toolデータベース用
   ```

### データベース命名規則

1. **データベース名**
   - フォーマット: `{APP_NAME}-{APP_ENV}-{接頭辞}`
   - 例: `nexus-local-sys`, `nexus-local-mst`, `nexus-local-trx1`

2. **テーブル名**
   - 必ず接頭辞をつける: `sys_`, `mst_`, `log_`, `trx_`, `adm_`, `tol_`
   - 単数形を使用（複数形にしない）
   - 詳細は`.claude/database.md`を参照

### Git操作のルール

1. **コミットのタイミング**
   - 意味のある単位でこまめにコミットする
   - 機能追加、バグ修正、リファクタリングなどを明確に分ける
   - コミットメッセージは日本語で記述する

2. **GitHubへのPUSH**
   - **重要: OpenCodeエージェントは自動的にGitHubにPUSHしません**
   - PUSHはユーザーが手動で行う必要があります
   - コミットまでは自動で行いますが、PUSHは明示的な指示があった場合のみ実行します
   - これは誤ったPUSHを防ぐための安全措置です

3. **ブランチ戦略**
   - 基本的に`main`ブランチで作業
   - 大きな機能追加の場合はfeatureブランチを作成することを検討
   - ブランチ名は日本語でも可（例: `feature/メールボックス機能`）

4. **リモートリポジトリ**
   - `git remote -v` で確認する
   - SSHエイリアスを使う場合は各自の `~/.ssh/config` で設定する

### 開発フロー

1. **新しいマイグレーションを作成する場合**
   ```bash
   # 適切なディレクトリに作成
   docker exec api-php php artisan make:migration create_mst_new_table --path=database/migrations/mst
   
   # マイグレーション実行
   docker exec api-php php artisan migrate --database=mst --path=database/migrations/mst
   ```

2. **trxテーブルを追加する場合**
   ```bash
   # trxディレクトリにマイグレーション作成
   docker exec api-php php artisan make:migration create_trx_new_table --path=database/migrations/trx
   
   # マイグレーションファイルに$connections配列を追加
   # protected $connections = ['trx1', 'trx2'];
   
   # trx1に対して実行（trx2にも自動適用される）
   docker exec api-php php artisan migrate --database=trx1 --path=database/migrations/trx
   ```

3. **マイグレーションのロールバック**
   ```bash
   # 個別データベース
   docker exec api-php php artisan migrate:rollback --database=sys --path=database/migrations/sys
   
   # 完全にリセット
   docker exec api-php php artisan migrate:fresh --database=sys --path=database/migrations/sys
   ```


