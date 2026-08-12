# Nexus

モバイルゲーム向けのLaravelベースのバックエンドフレームワーク。APIサーバーと運営ツールで構成されています。

## プロジェクト構成

```
Nexus/
├── api/                    # ゲームAPIサーバー
├── tool/                   # 運営管理ツール
├── docker/                 # Docker設定ファイル
├── command/                # 開発用スクリプト
│   ├── setup.sh           # 環境構築スクリプト
│   └── sail               # Laravel Sailラッパー
├── docker-compose.yml      # Docker Compose設定
├── .env                    # 環境変数（全プロジェクト共通）
└── .claude/                # プロジェクトドキュメント
    ├── development.md      # 開発環境構築ガイド
    ├── database.md         # データベース設計
    ├── api.md              # API仕様
    └── tool.md             # Tool仕様
```

## クイックスタート

### 1. 環境構築

```bash
# リポジトリをクローン
git clone <repository-url>
cd Nexus

# 環境変数ファイルをコピー（必要に応じて編集）
cp .env.example .env

# 環境を自動セットアップ
./command/setup.sh
```

`setup.sh`は以下を自動で実行します：
- Dockerコンテナの起動
- 全7データベースの作成と初期化
- 依存パッケージのインストール
- アセットのビルド
- データベースマイグレーションの実行

### 2. アプリケーションへのアクセス

- **API**: http://localhost:8090
- **Tool (運営管理画面)**: http://localhost:8091

## データベース構成

このプロジェクトは10個のMySQLデータベースを使用します。
うち trx / log はプレイヤー単位でシャーディングされます。

### 非シャーディングDB

| データベース | 用途 | ポート | プロジェクト |
|------------|------|--------|------------|
| **adm** | 管理者アカウント・権限管理 | 63060 | Tool |
| **tol** | 運営ツール機能（バナー、メンテナンス等） | 63061 | Tool |
| **sys** | システム管理（シャーディング、デプロイ、**プレイヤー公開情報**） | 63062 | API |
| **mst** | ゲームマスターデータ（アイテム、キャラクター等） | 63063 | API |

### シャーディングDB

trxとlogは**同じ番号どうしが1対1で対応**します（trx1の変更ログはlog1に記録される）。
シャード数は `.env` の `DB_TRX_SHARDS` で制御し、現在は **3** です。

| シャード | trx（プレイヤーデータ） | ポート | log（変更ログ・PITR） | ポート |
|---------|----------------------|--------|---------------------|--------|
| 1 | **trx1** | 63161 | **log1** | 63261 |
| 2 | **trx2** | 63162 | **log2** | 63262 |
| 3 | **trx3** | 63163 | **log3** | 63263 |

プレイヤーがどのシャードに属するかは sys データベースの
`sys_sharding` / `sys_sharding_node` / `sys_sharding_node_player` で管理します。
シャード追加の手順は [docs/sharding_expansion_guide.md](./docs/sharding_expansion_guide.md) を参照してください。

**sysデータベースの重要な役割**: プレイヤー名、レベル、戦闘力、パーティ編成などの「他プレイヤーから参照される公開情報」を保持します。これにより、ランキング表示やフレンド検索時にシャーディング先（trx1〜trxN）までアクセスせずに高速に情報を取得できます。

接続情報:
- ユーザー名: `root`
- パスワード: `root`
- データベース名: `{APP_NAME}-{APP_ENV}-{接頭辞}`（例: `nexus-local-sys`、`nexus-local-trx1`）

詳細は [.claude/database.md](./.claude/database.md) を参照してください。

## 開発コマンド

### 環境管理

```bash
# コンテナを起動
docker-compose up -d

# コンテナを停止
docker-compose stop

# コンテナとボリュームを完全に削除して再構築
./command/setup.sh
```

### マイグレーション

```bash
# APIプロジェクト - システムDB
docker exec api-php php artisan migrate --database=sys --path=database/migrations/sys

# APIプロジェクト - マスターDB
docker exec api-php php artisan migrate --database=mst --path=database/migrations/mst

# APIプロジェクト - トランザクションDB（すべてのシャード: trx1, trx2, ...）
docker exec api-php php artisan trx:migrate

# APIプロジェクト - ログDB（すべてのシャード: log1, log2, ...）
docker exec api-php php artisan pitr:migrate

# Toolプロジェクト - 管理者DB（接続名は admin、マイグレーションのパスは adm）
docker exec tool-php php artisan migrate --database=admin --path=database/migrations/adm

# Toolプロジェクト - 運営ツールDB（接続名は tool、マイグレーションのパスは tol）
docker exec tool-php php artisan migrate --database=tool --path=database/migrations/tol
```

**シャーディング対応マイグレーション:**
- `migrate:shards` - すべてのトランザクションシャード（trx1, trx2, ...）に一括マイグレーション
- `migrate:shards-status` - すべてのシャードのマイグレーション状態を確認
- `migrate:shards-rollback` - すべてのシャードでロールバック

詳細は [シャーディングマイグレーションシステム](./docs/sharding_migration_system.md) を参照してください。

### Laravel Sailラッパー

```bash
# API（デフォルト）
./command/sail artisan list
./command/sail composer install
./command/sail npm run dev

# Tool
./command/sail --tool artisan list
./command/sail --tool composer install
./command/sail --tool npm run dev
```

### テスト

```bash
# APIのテストを実行（Dockerを自動起動）
./command/sail api test

# Toolのテストを実行（Dockerを自動起動）
./command/sail tool test

# 特定のテストのみ実行
./command/sail api test --filter=UserTest

# より詳細な出力で実行
./command/sail api test --verbose
```

**注意**: テストコマンドは自動的にDockerコンテナの状態を確認し、起動していない場合は`docker-compose up -d`を実行します。

詳細は [.claude/development.md](./.claude/development.md) を参照してください。

## アーキテクチャ

### APIプロジェクト

- **Clean Architecture** ベース
- **DDD (Domain-Driven Design)** の概念を採用
- **Repository パターン** でデータアクセスを抽象化
- **シャーディング対応**: trx1/trx2で負荷分散

### Toolプロジェクト

- **MVC アーキテクチャ**
- **2つのデータベース**: admin（認証）とtool（業務機能）
- **Sanctum** による認証

## テーブル命名規則

すべてのテーブルには接頭辞が必要です：

- `sys_` - システム管理テーブル
- `mst_` - マスターデータテーブル
- `log_` - ログテーブル
- `trx_` - トランザクションテーブル
- `adm_` - 管理者関連テーブル
- `tol_` - 運営ツールテーブル

例: `mst_item`, `trx_player`, `log_gacha`, `tol_banner`

詳細は [.claude/database.md](./.claude/database.md) を参照してください。

## トラブルシューティング

### セットアップが失敗する

```bash
# 完全にクリーンアップして再実行
docker-compose down --volumes --remove-orphans
./command/setup.sh
```

### データベースに接続できない

```bash
# MySQLコンテナの状態確認
docker ps | grep db-

# ログ確認
docker logs db-sys
docker logs db-mst
```

### マイグレーションエラー

```bash
# 特定のデータベースをリセット
docker exec api-php php artisan migrate:fresh --database=sys --path=database/migrations/sys
```

## ドキュメント

### プロジェクト全体
- [アーキテクチャ](./.claude/architecture.md) - レイヤ構成、パッケージ分割の方針
- [コーディング規約](./.claude/coding-standards.md) - 実装ルール、レイヤごとの責務
- [命名規約](./.claude/naming-conventions.md) - クラス・テーブル・カラムの命名
- [開発環境構築ガイド](./.claude/development.md) - setup.sh、Docker、マイグレーション
- [データベース設計](./.claude/database.md) - テーブル構造、命名規則、マイグレーション管理
- [シャーディングマイグレーションシステム](./docs/sharding_migration_system.md) - 動的シャード対応、新規シャード追加手順

### API固有
- [API仕様](./.claude/api.md) - エンドポイント、認証、レスポンス形式
- [API呼び出しフロー](./api/docs/API_FLOW.md) - 推奨されるAPI呼び出し順序
- [コーディング規約](./api/docs/CODING_CONVENTIONS.md) - Request/Response/Dataクラスの命名規則とディレクトリ構成
- [Repositoryパターン実装ガイド](./api/docs/REPOSITORY_PATTERN.md) - データアクセス抽象化とキャッシュ戦略
- [クライアント認証](./docs/client_authentication.md) - 署名検証、デバイス認証

### 実装済み機能
- [ガチャシステム](./docs/gacha_implementation.md) - 通常/ステップアップ/ピックアップガチャ、確率制御、保証機能
- [ギルドシステム](./docs/guild_implementation.md) - ギルド作成、メンバー管理、申請承認、役職制御

### Tool固有
- [Tool仕様](./.claude/tool.md) - 運営ツールの機能、データベース構成

## ライセンス

このプロジェクトは [MIT License](./LICENSE) の下でライセンスされています。
