# 実装履歴 / Implementation History

このドキュメントには、プロジェクトの主要な実装内容とマイグレーション実行履歴が記録されています。

## 2026年8月4日

### VIPログインボーナスシステム実装

**概要**  
VIPレベル別のログインボーナス機能を実装。通常ログインボーナス、カムバックログインボーナスに続く3つ目のログインボーナス戦略。

**実装内容**

1. **データベース設計**
   - `mst_vip_login_bonus`: VIPレベル別設定マスター
   - `mst_vip_login_bonus_content`: VIPログインボーナス報酬内容
   - `trx_vip_login_bonus_history`: VIPログインボーナス受取履歴（シャーディング対応）

2. **Model層**
   - `MstVipLoginBonus` - VIPログインボーナス設定モデル
   - `MstVipLoginBonusContent` - VIPログインボーナスコンテンツモデル
   - `TrxVipLoginBonusHistory` - VIPログインボーナス履歴モデル

3. **Repository層**
   - `VipLoginBonusRepositoryInterface` - VIPログインボーナスRepositoryインターフェース
   - `MstVipLoginBonusRepository` - VIPログインボーナスRepository実装
   - `VipLoginBonusHistoryRepositoryInterface` - 履歴Repositoryインターフェース
   - `TrxVipLoginBonusHistoryRepository` - 履歴Repository実装

4. **Service層**
   - `VipLoginBonusService` - VIPログインボーナス配布サービス
     - `_BaseLoginBonusService`を継承
     - VIPレベル別データ取得ロジックを追加
     - ループ有・スキップなし（通常ログインボーナスと同じ動作）

5. **ServiceProvider設定**
   - `AppServiceProvider`にDIバインド追加
   - `LoginBonusOrchestrator`に優先度150で戦略登録
   - 実行優先度: カムバック(200) > VIP(150) > 通常(100)

**設計パターン**
- Template Method Pattern: `_BaseLoginBonusService`の通常動作を継承
- Strategy Pattern: `LoginBonusOrchestrator`で複数ボーナスを統合管理

**マイグレーション実行**

```bash
# 環境完全リセット（データベースボリューム削除）
bash command/setup.sh
```

**実行結果**
- すべてのマイグレーション正常完了
- VIPログインボーナステーブル作成完了
  - `mst_vip_login_bonus`
  - `mst_vip_login_bonus_content`
  - `trx_vip_login_bonus_history` (trx1/trx2両方)

**関連ファイル**
- マイグレーション: `api/database/migrations/mst/2026_08_04_000004_create_vip_login_bonus_tables.php`
- マイグレーション: `api/database/migrations/trx/2026_08_04_000004_create_vip_login_bonus_history_table.php`
- Model: `api/app/Models/Mst/MstVipLoginBonus.php`
- Model: `api/app/Models/Mst/MstVipLoginBonusContent.php`
- Model: `api/app/Models/Trx/TrxVipLoginBonusHistory.php`
- Repository: `api/app/Repositories/Mst/MstVipLoginBonusRepository.php`
- Repository: `api/app/Repositories/Trx/TrxVipLoginBonusHistoryRepository.php`
- Service: `api/app/Domain/Login/Services/VipLoginBonusService.php`
- Provider: `api/app/Providers/AppServiceProvider.php`
- ドキュメント: `docs/vip_login_bonus_design.md`

**コーディング規約遵守**
- ✅ CustomCollection使用（Illuminate\Support\Collection不使用）
- ✅ 基底クラス命名規則（`_Base`プレフィックス）
- ✅ 統一コンテンツ構造（content_type/content_mst_id/content_option/content_quantity/amount）
- ✅ シャーディング対応（trx1/trx2）
- ✅ Template Method Pattern実装

**次のステップ**
- VIPログインボーナス用のSeeder作成（VIPレベル0〜10の設定データ）
- 単体テスト作成（VipLoginBonusServiceTest）
- 統合テスト作成（複数ボーナス並行受取、VIPレベル変動時の動作確認）

---

### カムバックログインボーナスシステム実装（2026年8月4日以前）

**概要**  
一定期間ログインしていなかったプレイヤーが復帰した際に特別な報酬を付与する機能を実装。

**実装内容**
- `mst_login_bonus`テーブル拡張（type='comeback'対応）
- `ComeBackLoginBonusService`実装
- カムバック対象判定ロジック（N日以上未ログイン）
- ループなし・スキップあり（通常ログインボーナスとの差分）

**関連ドキュメント**
- `docs/comeback_login_bonus_design.md`
- `docs/comeback_login_bonus_usage.md`
- `docs/comeback_login_bonus_extension.md`

---

### VIPシステム実装（2026年8月4日以前）

**概要**  
VIPポイントに基づくVIPレベル管理システムを実装。

**実装内容**
- `mst_vip_level`: VIPレベル定義（VIP0〜VIP10）
- `mst_vip_level_reward`: VIPレベル到達報酬
- `log_vip_point`: VIPポイント変動ログ
- `sys_player`にVIPカラム追加（vip_point/vip_level）
- `VipService`: VIPレベル計算、報酬配布
- `VipServiceProvider`: 自動検出とDI登録

**特徴**
- VIPレベルは`vip_point`から動的計算
- 課金商品ごとにVIPポイント設定（`mst_in_app_purchase.vip_point`）
- VIPレベルアップ時の自動報酬配布

---

## マイグレーション管理

### 重要なルール

1. **setup.shの使用**
   - 初回セットアップ時のみ実行
   - 既存データを完全削除するため、開発途中では使用しない
   - 本番環境では絶対に実行しない

2. **個別マイグレーション実行**
   ```bash
   # APIプロジェクト
   docker exec api-php php artisan migrate --database=mst --path=database/migrations/mst
   docker exec api-php php artisan migrate --database=trx1 --path=database/migrations/trx
   
   # Toolプロジェクト
   docker exec tool-php php artisan migrate --database=adm --path=database/migrations/adm
   ```

3. **trxマイグレーションの特殊性**
   - `$connections = ['trx1', 'trx2']`配列で両シャードに適用
   - trx1に実行すればtrx2にも自動反映

### トラブルシューティング

**データベース接続エラーが発生した場合**

```bash
# コンテナ起動確認
docker ps

# データベースコンテナ再起動
docker compose restart db-mst db-sys db-trx1 db-trx2 db-log

# データベース接続確認
docker compose exec db-mst mysqladmin ping -h localhost -u root -proot
```

**MySQLバージョン互換性エラーが発生した場合**

```bash
# ボリューム完全削除して再セットアップ
docker compose down -v
bash command/setup.sh
```

## 参考リンク

- [開発環境構築](./development.md)
- [データベース設計](./database.md)
- [コーディング規約](./coding-standards.md)
- [命名規則](./naming-conventions.md)
