# Laravel Mobile RPG Server - ライブラリ化プロジェクト進捗レポート

生成日: 2026-08-03（更新）

## プロジェクト概要

LaravelモバイルRPGサーバーのフレームワーク部分を再利用可能なComposerパッケージとして分離するプロジェクト。

---

## パッケージ移行の完了定義（P2-1）

### ゴール設定

**「`api/app/Domain` 配下にビジネスロジックを置かない。apiはEloquent Repository実装・DI束ね・Controllerのアダプタ層のみ」**

### 完了条件

#### 1. api/app/Domain配下の役割を明確化

以下のみを許可：

- ✅ **Controller**: HTTPリクエスト/レスポンス処理、バリデーション、認証
- ✅ **UseCase**: ビジネスフロー制御（トランザクション管理のみ、ロジックはServiceに委譲）
- ✅ **Repository実装**: Eloquentモデルとパッケージインターフェースの橋渡し
- ✅ **Response DTO**: HTTPレスポンス用のデータ構造
- ✅ **Adapter**: パッケージインターフェースの実装（例: PlayerLevelServiceAdapter）

以下は禁止（パッケージへ移行必須）：

- ❌ **Service層のビジネスロジック**: 計算、バリデーション、状態変更ロジック
- ❌ **Domain Model**: エンティティ、値オブジェクト
- ❌ **複雑な計算ロジック**: 経験値計算、ガチャ抽選など

#### 2. 二重実装の削除

現在、以下のパターンで二重実装が存在：

| 機能 | api/app/Domain | packages | 状態 |
|------|---------------|----------|------|
| ログインボーナス | Domain/Login/Services | nexus-login | 🔄 要統合 |
| ガチャ | Domain/Gacha/Services | nexus-gacha | 🔄 要統合 |
| スタミナ | Domain/Stamina/Services | nexus-stamina | ✅ パッケージ優先 |
| プレイヤー管理 | Domain/Player/Services | nexus-core | 🔄 要統合 |
| リソース配送 | Domain/Resource/Services | nexus-resource-delivery | ✅ パッケージ優先 |

**完了条件**: すべてのビジネスロジックがパッケージに集約され、api側は薄いアダプタ層のみ

#### 3. 残タスクの一元管理

このドキュメント（library_progress.md）に以下を明記：

- [ ] 各パッケージの移行完了チェックリスト
- [ ] api側に残すファイル一覧
- [ ] 削除すべきファイル一覧
- [ ] テスト移行計画（P2-2と連携）

---

### 1. ✅ laravel-utilities (完了)

**場所**: `packages/laravel-utilities/`

**内容**:
- `ClockUtility`: 固定時刻管理ユーティリティ
- `RedisUtility`: Redis操作ヘルパー

**名前空間**: `LaravelUtilities\`

**依存**:
- illuminate/support ^11.0|^12.0
- illuminate/cache ^11.0|^12.0
- nesbot/carbon ^2.0|^3.0

**アプリ側の変更**:
- ✅ composer.jsonに追加
- ✅ `App\Utilities\Clock` → `LaravelUtilities\ClockUtility`に置換（16ファイル）
- ✅ 旧Utilitiesディレクトリを削除

**状態**: 完全に動作可能

---

### 2. ✅ laravel-security-middleware (完了)

**場所**: `packages/laravel-security-middleware/`

**内容**:
- **VerifyClientSignature**: HMAC-SHA256署名検証（リプレイ攻撃防止）
- **IdempotencyMiddleware**: 冪等性保証（重複リクエスト検出・gzip圧縮）
- **ThrottleSignUp**: サインアップのレート制限（IP/デバイスID別）
- **VerifyAccessToken**: アクセストークン検証（抽象化済み）

**名前空間**: `LaravelSecurityMiddleware\`

**依存**:
- illuminate/support ^11.0|^12.0
- illuminate/http ^11.0|^12.0
- illuminate/cache ^11.0|^12.0

**インターフェース**:
- `TokenValidatorInterface`: トークン検証ロジックの抽象化
- `PlayerSessionInterface`: セッション管理の抽象化

**設定ファイル**: `src/Config/security.php`

**ServiceProvider**: `SecurityMiddlewareServiceProvider`

**アプリ側の変更**:
- ✅ composer.jsonに追加
- ⏳ 未完了: ミドルウェアの置き換え（旧ミドルウェアはまだ存在）
- ⏳ 未完了: TokenValidatorInterfaceの実装
- ⏳ 未完了: PlayerSessionInterfaceの実装

**状態**: パッケージは完成、アプリ側の統合が残っている

---

## 進行中のパッケージ

### 3. 🔄 laravel-unit-of-work-core (設計中)

**予定場所**: `packages/laravel-unit-of-work-core/`

**目的**: QueryManager（Unit of Workパターン）とRepository/Model基底クラスのパッケージ化

#### 含めるべきコンポーネント

##### A. Persistence層
```
src/Persistence/
├── QueryManager.php              # Unit of Work本体
├── ApiSession.php                # リクエストコンテキスト管理（要抽象化）
└── QueryManager/
    ├── OperationCollector.php    # モデル収集・分類
    ├── BatchExecutor.php         # バッチクエリ実行
    └── UpdateQueryBuilder.php    # 相対的UPDATE SQL構築
```

##### B. Models層
```
src/Models/
├── BaseModel.php                 # 最上位基底クラス
├── BaseModelInterface.php
├── Trx/
│   ├── BaseTrx.php              # トランザクションデータ
│   └── BaseTrxInterface.php
├── Sys/
│   ├── BaseSys.php              # システム共通データ
│   └── BaseSysInterface.php
├── Log/
│   ├── BaseLog.php              # ログデータ
│   └── BaseLogInterface.php
└── Mst/
    ├── BaseMst.php              # マスターデータ
    └── BaseMstInterface.php
```

**BaseTrxの特徴**:
- 相対的な変更のサポート (`SET amount = amount + 10`)
- `relativeChanges`配列で増減を記録
- `selectKey`, `uniqueKeys`でデータ識別

##### C. Repositories層
```
src/Repositories/
├── BaseRepository.php            # 全Repository基底
├── BaseRepositoryInterface.php
├── Mst/
│   ├── BaseMstRepository.php    # 読み取り専用
│   └── BaseMstRepositoryInterface.php
├── Trx/
│   ├── BaseTrxRepository.php    # Unit of Work管理
│   └── BaseTrxRepositoryInterface.php
├── Sys/
│   ├── BaseSysRepository.php    # Unit of Work管理
│   └── BaseSysRepositoryInterface.php
└── Log/
    ├── BaseLogRepository.php    # Unit of Work管理（INSERTのみ）
    └── BaseLogRepositoryInterface.php
```

#### 依存関係と抽象化が必要な箇所

##### 1. ApiSession の抽象化

**現在の問題**:
- シャーディングロジックが埋め込まれている
- `sys_sharding_node`テーブルへの直接アクセス

**解決策**:
```php
// 新しいインターフェース
interface ConnectionResolverInterface {
    public function resolveConnection(int $playerId): string;
}

interface RequestContextInterface {
    public static function getPlayerId(): ?int;
    public static function setPlayerId(int $playerId): void;
    public static function getNow(): CarbonImmutable;
}

// アプリ側で実装
class ShardingConnectionResolver implements ConnectionResolverInterface {
    public function resolveConnection(int $playerId): string {
        // sys_sharding_nodeテーブルを参照
        // 'trx1' or 'trx2' を返す
    }
}
```

##### 2. 設定ファイル

```php
// config/unit-of-work.php
return [
    // データベース接続名のマッピング
    'connections' => [
        'mst' => env('DB_CONNECTION_MST', 'mst'),
        'sys' => env('DB_CONNECTION_SYS', 'sys'),
        'trx' => env('DB_CONNECTION_TRX', 'trx'),  // デフォルト接続名
        'log' => env('DB_CONNECTION_LOG', 'log'),
    ],
    
    // シャーディング設定
    'sharding' => [
        'enabled' => env('SHARDING_ENABLED', false),
        'resolver' => \App\Services\ShardingConnectionResolver::class,
    ],
    
    // QueryManager設定
    'query_manager' => [
        'purchase_log_transaction' => true,  // 課金ログを別トランザクションで処理
    ],
];
```

#### 実装の優先順位

**Phase 1: Model層（1日）**
1. BaseModel/BaseModelInterfaceをコピー
2. 名前空間を`LaravelUnitOfWork\Models`に変更
3. BaseTrx/BaseSys/BaseLog/BaseMstをコピー
4. インターフェースも同様に移行

**Phase 2: Repository層（1日）**
1. BaseRepository/BaseRepositoryInterfaceをコピー
2. 名前空間を`LaravelUnitOfWork\Repositories`に変更
3. 各DB別の基底Repositoryをコピー
4. QueryManagerへの依存を確認

**Phase 3: Persistence層（2-3日）**
1. QueryManager/OperationCollector/BatchExecutor/UpdateQueryBuilderをコピー
2. ApiSessionを抽象化:
   - `RequestContextInterface`を作成
   - `ConnectionResolverInterface`を作成
   - シャーディングロジックを外部化
3. ClockUtilityへの依存を`LaravelUtilities\ClockUtility`に変更

**Phase 4: 設定とServiceProvider（半日）**
1. `config/unit-of-work.php`を作成
2. `UnitOfWorkServiceProvider`を作成
3. composer.jsonとREADME.mdを作成

**Phase 5: アプリ側の統合（1-2日）**
1. ConnectionResolverの実装
2. RequestContextの実装（ApiSessionを拡張）
3. AppServiceProviderでバインド
4. 旧Model/Repositoryを新パッケージのものに置換
5. 名前空間の一括置換
6. テスト実行

#### 技術的な課題

**課題1: 相対的変更の処理**
- BaseTrxの`relativeChanges`配列
- UpdateQueryBuilderでの`SET column = column + value`生成
- 競合状態の回避が目的

**課題2: シャーディング**
- プレイヤーIDごとにDB接続を切り替える
- `sys_sharding_node`テーブルへの依存
- 動的な接続名の解決

**課題3: バッチ処理の最適化**
- INSERT/UPDATE/DELETEのバッチ実行
- ログの分離（購入ログは別トランザクション）
- sys_playerのみ個別INSERT（IDを取得するため）

**課題4: トランザクション管理**
- 複数DB（sys/trx/log）の協調トランザクション
- 課金ログの特別扱い
- ロールバック処理

#### 見積もり時間

- **Model層**: 1日
- **Repository層**: 1日
- **Persistence層**: 2-3日
- **設定・Provider**: 半日
- **アプリ統合**: 1-2日
- **テスト・調整**: 1-2日

**合計**: 約7-10日間

#### パッケージの価値

**メリット**:
- ✅ トランザクションデータの一括処理（パフォーマンス向上）
- ✅ 競合状態の回避（相対的変更のサポート）
- ✅ コードの重複削減（新規プロジェクトで再利用）
- ✅ ベストプラクティスの標準化

**デメリット**:
- ⚠️ 複雑性が高い
- ⚠️ シャーディングの抽象化が難しい
- ⚠️ 他プロジェクトへの適用には学習コストが必要

---

## 今後のアクション

### P2-2: テスト配置の一本化（優先度：中）

#### 現状の問題

現在、テストが以下のように二重管理されている：

1. **パッケージ側**: `packages/*/tests/` - ユニットテスト
2. **API側**: `api/tests/Feature/` - 統合テスト
3. **重複**: 同じロジックのテストがpackagesとapi両方に存在

#### ゴール

**「ロジックテスト = packages / 結線テスト = api の Feature テスト」**

#### 完了条件

##### 1. テスト配置ルールの明確化

| テスト種類 | 配置場所 | 目的 | 例 |
|-----------|---------|------|-----|
| ユニットテスト | `packages/*/tests/Unit/` | パッケージ内の単一クラス・メソッドの動作検証 | `GachaServiceTest.php` |
| 統合テスト（パッケージ内） | `packages/*/tests/Integration/` | パッケージ内の複数クラスの協調動作 | `LoginBonusFlowTest.php` |
| Feature テスト | `api/tests/Feature/` | HTTPエンドポイント〜DB〜レスポンスの全体動作 | `AuthFlowTest.php` |
| アダプタテスト | `api/tests/Unit/` | Eloquent Repository実装の動作確認のみ | `SysPlayerRepositoryTest.php` |

##### 2. 重複テストの削除

- [ ] api/tests/Unit配下のビジネスロジックテストをpackages側に移行
- [ ] api側は薄いアダプタ層のテストのみ残す
- [ ] packages側のテストカバレッジを80%以上に維持

##### 3. テスト実行環境の統一

```bash
# パッケージ個別テスト
cd packages/nexus-login && vendor/bin/phpunit

# API全体テスト（Feature）
cd api && php artisan test --testsuite=Feature

# 全パッケージ一括テスト（CI用）
./scripts/test-all-packages.sh
```

#### 移行手順

1. **Phase 1**: パッケージ側のテストを充実（1週間）
   - 各パッケージにtests/Unit, tests/Integrationディレクトリを作成
   - ビジネスロジックテストを移植

2. **Phase 2**: api側の重複テストを削除（3日）
   - api/tests/Unitから移行済みテストを削除
   - Featureテストは残す

3. **Phase 3**: CI設定を更新（1日）
   - GitHub Actionsでpackagesとapiを分離実行
   - テストカバレッジレポート生成

---

### 即座に実施すべき項目

1. **laravel-security-middlewareの統合完了**
   - [ ] TokenValidatorの実装クラスを作成
   - [ ] PlayerSessionInterfaceをApiSessionに実装
   - [ ] AppServiceProviderでバインド
   - [ ] 旧ミドルウェアファイルを削除
   - [ ] ミドルウェアの参照を新パッケージに変更

2. **設定ファイルの公開**
   ```bash
   php artisan vendor:publish --tag=laravel-security-middleware-config
   ```

3. **.envファイルの更新**
   ```env
   CLIENT_SECRET=your-secret-key
   CLIENT_SIGNATURE_TIMESTAMP_TOLERANCE=300
   CLIENT_SIGNATURE_NONCE_CACHE_TTL=600
   IDEMPOTENCY_ENABLED=true
   IDEMPOTENCY_CACHE_TTL=86400
   THROTTLE_SIGNUP_MAX_ATTEMPTS_PER_IP=10
   THROTTLE_SIGNUP_MAX_ATTEMPTS_PER_DEVICE=3
   ```

### 中期的な項目（1-2週間）

1. **laravel-unit-of-work-coreの実装**
   - Phase 1から順次実装
   - 各Phaseごとにテスト

2. **既存コードのリファクタリング**
   - 旧UtilitiesディレクトリがまだAppServiceProviderなどで参照されていないか確認
   - 名前空間の一貫性チェック

### 長期的な項目（1ヶ月以上）

1. **laravel-mobile-rpg-frameworkの作成**
   - BaseUseCase/UseCaseTrait
   - 認証Trait
   - トランザクション管理パターン

2. **ドキュメントの充実**
   - 各パッケージの詳細なドキュメント
   - アーキテクチャ図
   - ベストプラクティスガイド

3. **テストの追加**
   - 各パッケージのユニットテスト
   - 統合テスト

4. **CI/CDの設定**
   - GitHub Actionsでの自動テスト
   - Packagistへの自動公開

---

## ファイル構造

### 現在の packages/ ディレクトリ

```
packages/
├── laravel-utilities/
│   ├── src/
│   │   ├── ClockUtility.php
│   │   └── RedisUtility.php
│   ├── composer.json
│   └── README.md
│
└── laravel-security-middleware/
    ├── src/
    │   ├── Middleware/
    │   │   ├── VerifyClientSignature.php
    │   │   ├── IdempotencyMiddleware.php
    │   │   ├── ThrottleSignUp.php
    │   │   └── VerifyAccessToken.php
    │   ├── Contracts/
    │   │   ├── TokenValidatorInterface.php
    │   │   └── PlayerSessionInterface.php
    │   ├── Config/
    │   │   └── security.php
    │   └── SecurityMiddlewareServiceProvider.php
    ├── composer.json
    └── README.md
```

### 将来の packages/ ディレクトリ（予定）

```
packages/
├── laravel-utilities/                    ✅ 完了
├── laravel-security-middleware/          ✅ 完了
├── laravel-unit-of-work-core/            🔄 設計中
└── laravel-mobile-rpg-framework/         ⏳ 未着手
```

---

## 技術スタック

- **PHP**: 8.2+
- **Laravel**: 11.0 or 12.0
- **データベース**: MySQL（Mst/Sys/Trx/Log の4つのDB）
- **キャッシュ**: Redis
- **パターン**: Unit of Work, Repository, Value Object

---

## まとめ

### 達成したこと
- ✅ 2つのパッケージを完成（laravel-utilities, laravel-security-middleware）
- ✅ 依存関係の整理と抽象化
- ✅ Composerパッケージとしての基本構造を確立

### 次のステップ
1. laravel-security-middlewareのアプリ統合を完了
2. laravel-unit-of-work-coreの段階的実装（Phase 1から）
3. 各パッケージのテスト追加

### 推定残り時間
- laravel-security-middleware統合: 2-3時間
- laravel-unit-of-work-core: 7-10日間
- laravel-mobile-rpg-framework: 3-5日間

**合計**: 約2-3週間でフレームワーク全体のライブラリ化が完了する見込み

---

## 連絡先・リソース

- GitHubリポジトリ: `/Users/s-nakamura/Github/LaravelMobileRPGServer`
- パッケージディレクトリ: `packages/`
- アプリケーションディレクトリ: `api/`

---

**このドキュメントは次回の作業再開時の参照用です。**
