# エージェント向け参照資料

毎回守る規約・コマンド・DB構成は、リポジトリ直下の [CLAUDE.md](../CLAUDE.md) にある。
このディレクトリには、必要になったときに読む詳細を置く。

`.opencode` はこのディレクトリへのシンボリックリンクで、opencode も同じ内容を読む（`api/tests/Architecture/AgentDocsInSyncTest.php` が検証）。opencode はルートの `CLAUDE.md` を自動で読むので、`opencode.json` に `instructions` は書かない。

## 参照資料

| ファイル | 内容 |
|---|---|
| [architecture.md](./architecture.md) | レイヤー構成、モジュール境界と公開API、ドメインイベント、パッケージとアプリの境界、UnitOfWork とトランザクション、ApiSession、例外とエラーコード、アーキテクチャテスト |
| [coding-standards.md](./coding-standards.md) | Controller / Request / UseCase / Service / Repository / Model / Response / DTO の書き方、時刻（ClockUtility）、例外の投げ方、テスト |
| [naming-conventions.md](./naming-conventions.md) | クラス・変数・メソッド・ディレクトリの命名 |
| [database.md](./database.md) | DB構成とシャーディング、マイグレーション、テーブル・カラム規約、l10n、deploy_key、ログテーブル、通貨 |
| [api.md](./api.md) | ルーティングと認証、リクエスト・レスポンスの規約、エラー形式、バージョンチェックとマスターデータ配信 |
| [development.md](./development.md) | コンテナとポート、make コマンド、テスト・カバレッジ・静的解析、困ったとき |
| [tool.md](./tool.md) | 運営ツール（`tool/`）の構成と機能 |

## スキル

`.claude/skills/` には、特定の作業のときだけ読み込まれる手順書がある。`/<スキル名>` で呼び出せるほか、会話の内容に応じて自動で読み込まれる。

- [create-skill](./skills/create-skill/SKILL.md): スキルの新規作成・改訂・デバッグ
- [cs-support](./skills/cs-support/SKILL.md): CS問い合わせの調査・原因特定・Issue化

## 書くときの決まり

- 1つの話題は1つのファイルにだけ書く。ほかのファイルからは参照する。
- コードやテストを見れば分かること（DDL、クラスの中身、エラーコードの一覧）は写さず、ファイルパスで示す。写すと古くなる。
- 架空のクラス名で例を書かない。実在するクラスを示す。
- 毎回守るべき規約が増えたら、`CLAUDE.md` に1行で足す。詳細はここの資料に書く。
