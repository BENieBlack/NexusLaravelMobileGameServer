# 症状別 調査先マップ

シャード特定（SKILL.md 手順3）を済ませてから使うこと。`<N>` は該当シャード番号。
DB名は `nexus-{APP_ENV}-{接尾辞}`（ローカルは `nexus-local-trx1` など）。

## 共通の起点

| 見るもの | 場所 |
|---|---|
| プレイヤー基本情報・VIP | `sys.sys_player` |
| 端末・トークン | `sys.sys_player_device` / `sys.sys_player_token` |
| シャード割り当て | `sys.sys_sharding_node_player` → `sys.sys_sharding_node` |
| アクセス履歴 | `log<N>.log_access` |
| 全般の変更履歴（PITR） | `log<N>.log_trx_change` / `log<N>.log_trx_*` |
| レイヤー構造 | Controller → UseCase → Service → Repository → Model |

`log_*` はゲーム内行動ログ、`log_trx_*` はPITR用の変更履歴。**「消えた」「戻らない」系は `log_trx_*`** を見る。

---

## ログインボーナスが受け取れない / 重複した

| | |
|---|---|
| テーブル | `trx<N>.trx_login_bonus_history` / `trx<N>.trx_vip_login_bonus_history` |
| ログ | `log<N>.log_trx_login_bonus_history` / `log<N>.log_trx_vip_login_bonus_history` |
| 実装 | `packages/nexus-login/`、`api/app/Domain/Login/Services/` |
| 設計書 | `docs/comeback_login_bonus_design.md` / `docs/vip_login_bonus_design.md` |

**必ず確認する点**

1. **日跨ぎ判定** — カレンダー日ではなく `DAY_START_TIME`（`.env` 未設定時 `00:00:00`）基準。`ClockUtility::getGameDayStart()` / `isSameGameDay()` を通した値で判断する。ユーザーの申告時刻をそのまま日付比較すると誤判定する
2. **戦略の優先度** — `api/app/Providers/AppServiceProvider.php:287` 付近で登録。カムバック(200) > VIP(150) > 通常(100)。優先度が高い戦略が成立すると下位の挙動が変わるので、「通常のが来ない」はカムバック/VIPが先に成立していないか見る
3. **戦略ごとの挙動差** — 通常とVIPはループ有・スキップなし、カムバックはループなし・スキップあり
4. **VIPレベル** — `sys_player.vip_level` は `vip_point` から動的計算。問い合わせ時点とボーナス受取時点でレベルが違う可能性がある（`log<N>.log_vip_point` で変動を追う）

---

## 課金したのに反映されない / 二重課金

| | |
|---|---|
| テーブル | `trx<N>.trx_in_app_purchase` / `trx<N>.trx_in_app_purchase_effect` |
| ログ | `log<N>.log_in_app_purchase` / `log<N>.log_trx_in_app_purchase` |
| 実装 | `packages/nexus-core-billing/`、`api/app/Domain/InAppPurchase/` |

**必ず確認する点**

1. **レシート検証で落ちていないか** — `nexus-core-billing/src/ApiClients/GooglePlayApiClient.php` 等。SDKはオプション依存なので、環境によってクラス未解決で落ちることがある
2. **購入と付与が分離している** — `trx_in_app_purchase`（購入記録）と `trx_in_app_purchase_effect`（付与結果）の両方を見る。片方だけあるなら付与処理の途中で失敗している
3. **ステータス** — `log_in_app_purchase` は Purchased / Failed / Refunded / CheckAvailability を持つ。Refunded の申告は返金済みでないか先に確認する
4. **通貨の内訳** — `trx_wallet` / `trx_wallet_balance`、有償/無償の区別は `trx_diamond` / `trx_diamond_balance`。「石が減っている」は有償・無償どちらが減ったかで扱いが変わる

---

## ガチャの結果がおかしい

| | |
|---|---|
| テーブル | `trx<N>.trx_gacha` / `trx<N>.trx_gacha_history` |
| ログ | `log<N>.log_gacha` / `log<N>.log_trx_gacha` |
| 実装 | `packages/nexus-gacha/`、`api/app/Domain/Gacha/` |
| 設計書 | `docs/gacha_system_design.md` / `docs/gacha_implementation.md` |

**必ず確認する点**

1. `log_gacha` の抽選結果と `trx_unit` / `trx_item` の付与結果が一致しているか。ズレていれば付与処理の問題、一致していれば確率の説明の問題
2. 天井・ピックアップの状態は `trx_gacha` の累計値を見る
3. 提供割合の申告差異は、その時点のマスタ（`mst` DB）が現在と同じか確認する。マスタは `sys_deploy_master` でバージョン管理されている

---

## アイテム／ユニット／装備が消えた

| | |
|---|---|
| テーブル | `trx<N>.trx_item` / `trx_unit` / `trx_equipment` |
| ログ | `log<N>.log_trx_item` / `log_trx_unit` / `log_trx_equipment`、`log_item` / `log_unit` / `log_equipment` |
| 実装 | `packages/nexus-resource/`、`api/app/Domain/Item/` `Unit/` `Equipment/` |
| 復旧 | `docs/trx_point_in_time_recovery.md` / `packages/nexus-pitr/` |

**必ず確認する点**

1. **`log_trx_*` に減算の履歴があるか** — あれば正規の消費（本人操作）。無ければデータ欠損の疑い
2. 消費した機能を特定する（強化素材、進化、トレード `trx_player_sns`、売却）
3. 欠損が確認できた場合のみPITRの検討に進む。**このスキルでは復旧を実行しない**

---

## メールが受け取れない / 消えた

| | |
|---|---|
| テーブル | `trx<N>.trx_mailbox` |
| ログ | `log<N>.log_trx_mailbox` |
| 実装 | `packages/nexus-mailbox/`、`api/app/Domain/Mailbox/` |
| 設計書 | `docs/MAILBOX_DESIGN.md` |

**必ず確認する点** — 受取期限切れが最頻出。次に、そもそも配信対象だったか（配信条件・配信時点のプレイヤー状態）。

---

## ログインできない / データが消えた（アカウント）

| | |
|---|---|
| テーブル | `sys.sys_player` / `sys_player_device` / `sys_player_token` |
| ログ | `log<N>.log_access` / `log<N>.log_trx_player` / `log_trx_player_sns` |
| 実装 | `packages/nexus-core-auth/`、`packages/nexus-core-security/`、`api/app/Domain/Auth/` |
| 設計書 | `docs/client_authentication.md` |

**必ず確認する点**

1. `log_access` に到達記録があるか。無ければクライアント側かネットワーク、あればサーバー側
2. **メンテナンス中でないか** — `sys.sys_maintenance`。maintenance配下のAPIは一律で弾かれる
3. **バージョン不一致でないか** — `sys_deploy` / `sys_deploy_master` / `sys_deploy_asset`
4. 「データが消えた」の多くは別アカウントでのログイン。`sys_player_device` で端末とプレイヤーの対応を確認する
5. `sign_in` / `refresh_token` にはレート制限がある（`api/routes/api.php`）。連続失敗の申告では429を疑う

---

## スタミナが回復しない

| | |
|---|---|
| テーブル | `trx<N>.trx_stamina` |
| ログ | `log<N>.log_trx_stamina` |
| 実装 | `packages/nexus-stamina/`、`api/app/Domain/Stamina/` |

**必ず確認する点** — 回復は経過時間からの計算。`ClockUtility` 基準の時刻と、最終更新時刻のズレを見る。端末時計のズレの申告もここ。

---

## ギルドに入れない / 申請が届かない

| | |
|---|---|
| テーブル | `sys.sys_guild` / `sys_guild_member` / `sys_guild_apply` |
| 実装 | `packages/nexus-guild/`、`api/app/Domain/Guild/` |
| 設計書 | `docs/guild_implementation.md` |

**必ず確認する点** — ギルドは **sys DB（シャーディングされていない）**。シャードを跨ぐメンバーが同一ギルドに入るため、trx側の感覚で調べないこと。定員・申請の重複・既加入を順に見る。

---

## フレンド関連

| | |
|---|---|
| テーブル | `sys.sys_friend_apply` |
| 実装 | `packages/nexus-friend/`、`api/app/Domain/Friend/` |

ギルドと同じく sys DB。過去に全エンドポイントが500になる不具合があった（コミット `f31e690`）ため、同種の申告は再発を疑う。

---

## どれにも当てはまらない場合

1. `log<N>.log_access` で該当時刻のリクエストを特定する
2. エンドポイントから `api/routes/api.php` → Controller → UseCase → Service と辿る
3. `log<N>.log_trx_change` で該当時刻の変更を洗う
4. それでも不明なら「未確定」として、確定に必要な情報をIssueに書く
