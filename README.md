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

- **API**: http://localhost:8080
- **Tool (運営管理画面)**: http://localhost

## データベース構成

このプロジェクトは7つのMySQLデータベースを使用します：

| データベース | 用途 | ポート | プロジェクト |
|------------|------|--------|------------|
| **adm** | 管理者アカウント・権限管理 | 33060 | Tool |
| **tol** | 運営ツール機能（バナー、メンテナンス等） | 33061 | Tool |
| **sys** | システム管理（シャーディング、デプロイ、**プレイヤー公開情報**） | 33062 | API |
| **mst** | ゲームマスターデータ（アイテム、キャラクター等） | 33063 | API |
| **log** | ゲームログ（API、ガチャ、課金等） | 33064 | API |
| **trx1** | プレイヤートランザクションデータ（シャード1） | 33065 | API |
| **trx2** | プレイヤートランザクションデータ（シャード2） | 33066 | API |

**sysデータベースの重要な役割**: プレイヤー名、レベル、戦闘力、パーティ編成などの「他プレイヤーから参照される公開情報」を保持します。これにより、ランキング表示やフレンド検索時にシャーディング先（trx1/trx2）までアクセスせずに高速に情報を取得できます。

接続情報:
- ユーザー名: `root`
- パスワード: `root`
- データベース名: `arche-local-{接頭辞}`（例: `arche-local-sys`）

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
# APIプロジェクト - 全データベース
docker exec api-php php artisan migrate --database=sys --path=database/migrations/sys
docker exec api-php php artisan migrate --database=mst --path=database/migrations/mst
docker exec api-php php artisan migrate --database=log --path=database/migrations/log
docker exec api-php php artisan migrate --database=trx1 --path=database/migrations/trx

# Toolプロジェクト - 全データベース
docker exec tool-php php artisan migrate --database=adm --path=database/migrations/adm
docker exec tool-php php artisan migrate --database=tol --path=database/migrations/tol
```

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
- [開発ルール](./DEVELOPMENT_RULES.md) - アーキテクチャ、コーディング規約、データベース設計、API設計
- [開発環境構築ガイド](./.claude/development.md) - setup.sh、Docker、マイグレーション
- [データベース設計](./.claude/database.md) - テーブル構造、命名規則、マイグレーション管理

### API固有
- [API仕様](./.claude/api.md) - エンドポイント、認証、レスポンス形式
- [API呼び出しフロー](./api/docs/API_FLOW.md) - 推奨されるAPI呼び出し順序
- [コーディング規約](./api/docs/CODING_CONVENTIONS.md) - Request/Response/Dataクラスの命名規則とディレクトリ構成
- [Repositoryパターン実装ガイド](./api/docs/REPOSITORY_PATTERN.md) - データアクセス抽象化とキャッシュ戦略

### Tool固有
- [Tool仕様](./.claude/tool.md) - 運営ツールの機能、データベース構成

## ライセンス

このプロジェクトは [MIT License](./LICENSE) の下でライセンスされています。
