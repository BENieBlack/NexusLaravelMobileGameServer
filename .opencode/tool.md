# Tool (運営ツール) プロジェクト

## 概要

Toolプロジェクトは、ゲームの運営管理を行うための管理画面システムです。admデータベースとtolデータベースの2つを使用します。

## データベース構成

### adm データベース (`arche-local-adm`)

管理者アカウントと権限管理を担当するデータベース。

**テーブル一覧:**
- `adm_account` - 管理者アカウント
- `cache`, `cache_locks` - Laravelキャッシュ
- `jobs`, `job_batches`, `failed_jobs` - Laravelジョブキュー
- `migrations` - マイグレーション履歴

### tol データベース (`arche-local-tol`)

運営ツールの各種機能を担当するデータベース。

**テーブル一覧:**
- `tol_master_status` - マスターデータのステータス管理
- `tol_asset_status` - アセットのステータス管理
- `tol_banner` - バナー管理
- `tol_cache_control` - キャッシュ制御
- `tol_maintenance` - メンテナンス情報
- `tol_notice` - お知らせ管理
- `cache`, `cache_locks` - Laravelキャッシュ
- `jobs`, `job_batches`, `failed_jobs` - Laravelジョブキュー
- `migrations` - マイグレーション履歴

## アクセス情報

- **URL**: http://localhost
- **コンテナ名**: tool-php, tool-web
- **データベースホスト**: db-adm (ポート: 33060), db-tol (ポート: 33061)

## マイグレーション

### admデータベース

```bash
docker exec tool-php php artisan migrate --database=adm --path=database/migrations/adm
```

### tolデータベース

```bash
docker exec tool-php php artisan migrate --database=tol --path=database/migrations/tol
```

### 両方のデータベースを一括実行

```bash
docker exec tool-php php artisan migrate --database=adm --path=database/migrations/adm && \
docker exec tool-php php artisan migrate --database=tol --path=database/migrations/tol
```

## 開発ガイドライン

### 新しいテーブルを追加する場合

1. **マイグレーションファイルを作成**
   ```bash
   # admデータベース用
   docker exec tool-php php artisan make:migration create_adm_new_table --path=database/migrations/adm
   
   # tolデータベース用
   docker exec tool-php php artisan make:migration create_tol_new_table --path=database/migrations/tol
   ```

2. **マイグレーションファイルを編集**
   - admデータベース用: `Schema::connection('adm')`を使用
   - tolデータベース用: `Schema::connection('tol')`を使用

3. **マイグレーションを実行**
   ```bash
   docker exec tool-php php artisan migrate --database=adm --path=database/migrations/adm
   # または
   docker exec tool-php php artisan migrate --database=tol --path=database/migrations/tol
   ```

### テーブル命名規則

- **admデータベース**: `adm_`接頭辞を使用
  - 例: `adm_account`, `adm_role`, `adm_permission`
- **tolデータベース**: `tol_`接頭辞を使用
  - 例: `tol_banner`, `tol_maintenance`, `tol_notice`

### モデル配置

```
tool/app/Models/
├── Adm/              # admデータベースのモデル
│   └── AdmAccount.php
└── Tol/              # tolデータベースのモデル
    ├── TolBanner.php
    ├── TolMaintenance.php
    └── TolNotice.php
```

### モデルの接続指定

```php
namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;

class AdmAccount extends Model
{
    protected $connection = 'adm';
    protected $table = 'adm_account';
}
```

```php
namespace App\Models\Tol;

use Illuminate\Database\Eloquent\Model;

class TolBanner extends Model
{
    protected $connection = 'tol';
    protected $table = 'tol_banner';
}
```

## 注意事項

1. **adm vs tol の使い分け**
   - `adm`: アカウント管理、認証、権限など、セキュリティに関わる情報
   - `tol`: 運営業務に関わる機能（バナー、メンテナンス、お知らせなど）

2. **usersテーブルは使用しない**
   - Laravelのデフォルト`users`テーブルの代わりに`adm_account`テーブルを使用
   - 認証設定は`config/auth.php`で`AdmAccount`モデルを指定

3. **マイグレーションの実行順序**
   - admデータベースのマイグレーションを先に実行してください
   - tolデータベースのテーブルがadmデータベースのテーブルを参照する場合があるため
