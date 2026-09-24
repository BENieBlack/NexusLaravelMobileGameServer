# 開発ドキュメント / Development Documentation

このディレクトリには、Laravelモバイルゲームプロジェクトの開発ルールとガイドラインが含まれています。

> **📌 重要**: このプロジェクトでは**日本語**でコミュニケーションを行います。詳細は [language.md](./language.md) を参照してください。

## プロジェクト概要

このプロジェクトは、**モバイルゲームのバックエンドAPI**を提供するLaravelアプリケーションです。

### 主な特徴

- **レイヤードアーキテクチャ**: Controller → UseCase → Service → Model の4層構造
- **マルチデータベース構成**: 7つのデータベース（adm, tol, sys, mst, log, trx1, trx2）
- **シャーディング対応**: トランザクションデータを水平分割（trx1, trx2）
- **マスターデータ配信システム**: JSON + 差分更新 + ハッシュ管理
- **Deploy Key管理**: バージョニングとロールバック対応

## ドキュメント構成

### 📋 基本ドキュメント

| ファイル | 説明 |
|---------|------|
| [language.md](./language.md) | **言語設定（日本語で対応）** |
| [architecture.md](./architecture.md) | アーキテクチャ設計、レイヤー構造、依存関係ルール |
| [coding-standards.md](./coding-standards.md) | コーディング規約（Controller/UseCase/Service/Model等） |
| [naming-conventions.md](./naming-conventions.md) | 命名規約（DTO/Service/Handler/Utility等） |
| [database.md](./database.md) | データベース設計、命名規則、マイグレーション管理 |
| [api.md](./api.md) | API設計、ルーティング、レスポンス形式 |
| [development.md](./development.md) | 環境構築、Docker、マイグレーション実行方法 |
| [tool.md](./tool.md) | 運営ツールプロジェクトの説明 |
| [implementation-history.md](./implementation-history.md) | **実装履歴（マイグレーション実行記録含む）** |

### 📂 カテゴリ別詳細ドキュメント

#### API層の詳細

`.claude/api/`ディレクトリには、各レイヤーの詳細ルールがあります：

- [controller.md](./api/controller.md) - Controllerの実装詳細
- [service.md](./api/service.md) - Serviceの実装詳細
- [repository.md](./api/repository.md) - Repositoryの実装詳細
- [dto.md](./api/dto.md) - DTOの設計ルール
- [request.md](./api/request.md) - Requestの実装ルール
- [response.md](./api/response.md) - Responseの実装ルール
- [model.md](./api/model.md) - Eloquentモデルの実装ルール
- [utility.md](./api/utility.md) - Utilityクラスの実装ルール
- [api-session.md](./api/api-session.md) - ApiSession（プレイヤーID管理）の実装ルール
- [error.md](./api/error.md) - エラーハンドリング
- [interface.md](./api/interface.md) - インターフェース設計

#### データベース別詳細

`.claude/database/`ディレクトリには、各データベースの詳細設計があります：

- [sys.md](./database/sys.md) - sysデータベース（システム情報）
- [mst.md](./database/mst.md) - mstデータベース（マスターデータ）
- [trx.md](./database/trx.md) - trxデータベース（トランザクション）
- [log.md](./database/log.md) - logデータベース（ログデータ）
- [adm.md](./database/adm.md) - admデータベース（管理者情報）
- [tol.md](./database/tol.md) - tolデータベース（運営ツール用データ）

### 📁 `.opencode/` について

`.opencode` は `.claude` へのシンボリックリンクです（2026-08-20〜）。
以前は同じ内容を2箇所にコピーしていましたが、17,671行の完全な複製で、
片方だけ更新される事故が起きるため1つに統合しました。
**ドキュメントの追加・修正は `.claude/` 側だけ行えば両方に反映されます。**

### 🛠 スキル

`.claude/skills/`ディレクトリには、特定の作業のときだけ読み込まれる手順書があります。
`/<スキル名>` で明示的に呼び出せるほか、会話の内容に応じて自動で読み込まれます。

- [create-skill](./skills/create-skill/SKILL.md) - スキルの新規作成・改訂・デバッグ
- [cs-support](./skills/cs-support/SKILL.md) - CS問い合わせの調査・原因特定・Issue化

## クイックスタート

### 1. 環境構築

```bash
# セットアップスクリプトを実行
./command/setup.sh

# マイグレーション実行
./command/sail artisan migrate:all
```

詳細は [development.md](./development.md) を参照してください。

### 2. 新機能の追加

新しい機能を追加する際は、以下の順序で実装します：

1. **データベース設計** - [database.md](./database.md) を参照
2. **Modelの作成** - [api/model.md](./api/model.md) を参照
3. **DTOの作成** - [naming-conventions.md](./naming-conventions.md) を参照
4. **Serviceの実装** - [coding-standards.md](./coding-standards.md) を参照
5. **UseCaseの実装** - [coding-standards.md](./coding-standards.md) を参照
6. **Controllerの実装** - [coding-standards.md](./coding-standards.md) を参照
7. **APIルート定義** - [api.md](./api.md) を参照

### 3. コーディング規約の確認

- **命名規則**: [naming-conventions.md](./naming-conventions.md)
- **日時操作**: [coding-standards.md](./coding-standards.md#0-日時操作のルール)（CarbonImmutable統一）
- **Modelのルール**: [coding-standards.md](./coding-standards.md#6-model-の実装ルール)

## アーキテクチャの概要

```
┌─────────────────────────────────────┐
│  Presentation Layer (Controller)    │ ← HTTPリクエスト/レスポンス
├─────────────────────────────────────┤
│  Application Layer (UseCase)        │ ← ビジネスフロー制御
├─────────────────────────────────────┤
│  Domain Layer (Service/Repository)  │ ← ビジネスロジック
├─────────────────────────────────────┤
│  Infrastructure Layer (Model)       │ ← データアクセス
└─────────────────────────────────────┘
```

詳細は [architecture.md](./architecture.md) を参照してください。

## データベース構成

```
┌────────────────────────────────────────────┐
│  adm  │ 管理者アカウント・権限管理         │
│  tol  │ 運営ツール機能                     │
├────────────────────────────────────────────┤
│  sys  │ パブリック情報（デプロイ管理等）   │
│  mst  │ マスターデータ（アイテム・キャラ等）│
├────────────────────────────────────────────┤
│  log  │ ログデータ（insert only）          │
├────────────────────────────────────────────┤
│ trx1  │ プレイヤーデータ（シャード1）      │
│ trx2  │ プレイヤーデータ（シャード2）      │
└────────────────────────────────────────────┘
```

詳細は [database.md](./database.md) を参照してください。

## 重要な設計原則

### 1. 日時操作は CarbonImmutable で統一

```php
// ✅ Good
use Carbon\CarbonImmutable;
$now = CarbonImmutable::now();

// ❌ Bad
use Carbon\Carbon;
$now = Carbon::now();
```

### 2. DTOクラスにはサフィックスを付けない

```php
// ✅ Good
class DeliveryContent { }  // app/Domain/Delivery/DTOs/

// ❌ Bad
class DeliveryContentData { }  // 冗長
```

### 3. Controllerは薄く保つ

```php
// ✅ Good: 10行以内
public function version(VersionCheckRequest $request, CheckUseCase $useCase): JsonResponse
{
    $response = $useCase->handle($request);
    return $response->toJsonResponse();
}
```

### 4. ENUM型はマスターテーブルのみ

```php
// ✅ Good: mstテーブルではENUMを使用可能
$table->enum('status', ['active', 'inactive']);

// ❌ Bad: trx/sys/logテーブルではSTRINGを使用
$table->string('status')->comment('ステータス (active, inactive等)');
```

## よくある質問（FAQ）

### Q1. 新しいDTOクラスを作成する際の命名規則は？

**A1**: `DTOs/`ディレクトリに配置し、サフィックスなしで命名します。詳細は [naming-conventions.md](./naming-conventions.md) を参照してください。

### Q2. trxテーブルで新しい状態を追加したい。ENUM型を使うべき？

**A2**: いいえ、trxテーブルではENUM型を使用せず、STRING型を使用してください。ENUM型の拡張にはサービス停止が必要です。詳細は [database.md](./database.md#enum型の使用ルール) を参照してください。

### Q3. 日時操作でCarbonとCarbonImmutableのどちらを使うべき？

**A3**: 必ず`CarbonImmutable`を使用してください。予期しない変更を防ぐためです。詳細は [coding-standards.md](./coding-standards.md#0-日時操作のルール) を参照してください。

### Q4. Controllerにビジネスロジックを書いてもいい？

**A4**: いいえ、Controllerは薄く保ち、ビジネスロジックはServiceに実装してください。詳細は [coding-standards.md](./coding-standards.md#1-controller-の実装ルール) を参照してください。

### Q5. ログテーブルのModelで注意すべきことは？

**A5**: `_BaseLog`を継承し、`UPDATED_AT = null`を設定します。ログテーブルはinsert onlyです。詳細は [coding-standards.md](./coding-standards.md#log-model-の特別なルール) を参照してください。

## 参考

### 世界標準のベストプラクティス

本プロジェクトの設計は、以下のような大規模モバイルゲームで採用されている手法を参考にしています：

- **Genshin Impact**: JSON差分配信 + ローカルキャッシュ
- **Fate/Grand Order**: ハッシュベースの差分管理
- **ウマ娘**: マスターデータのバージョニング

## 更新履歴

| 日付 | バージョン | 変更内容 |
|------|-----------|---------|
| 2026-02-24 | 2.0.0 | ドキュメント構造を再編成（.claude/配下に整理） |

## フィードバック

このドキュメントに関する質問や改善提案があれば、チーム内で議論してください。
