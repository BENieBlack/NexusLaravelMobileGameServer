---
name: cs-support
description: Slackのnexus-csに来たCS問い合わせを調査し、原因を特定してGitHub Issueに記録するときに使う。過去の類似案件を先に検索し、シャード特定→ログ照合の順で原因を追う。「CS問い合わせ」「CS対応」「nexus-csのスレッド」「問い合わせを調査して」「ログインボーナスが受け取れないと問い合わせ」などSlackスレッドURLの提示とあわせて発動する。不具合の修正実装そのものには使わない（原因特定とIssue化までが範囲）。
---

# CS問い合わせ対応

## 前提（必ず先に読む）

### Slackは自動では読めない

このプロジェクトには**Slack連携（MCP・トークン・CLI）が一切設定されていません**。スレッドURLだけ渡されても本文は取得できません。

URLしか渡されなかった場合は、**推測で調査を始めずスレッド本文の貼り付けを依頼すること。** 症状の記述を誤解したまま調べると、無関係なコードを読んで見当違いの結論を出します。

自動取得したい場合は Slack MCP サーバーの追加が必要です。手順は [references/slack-setup.md](./references/slack-setup.md) を参照。

### このリポジトリは PUBLIC

`BENieBlack/NexusLaravelMobileGameServer` は公開リポジトリです。**Issueに個人情報を書かないこと。**

| Issueに書いてよい | Issueに書いてはいけない |
|---|---|
| 症状の類型、再現条件 | プレイヤーID、端末ID、トークン |
| 原因、該当コード、該当テーブル | 課金レシート、注文ID、メールアドレス |
| 対応方針、修正コミット | ユーザー名、問い合わせ本文の引用 |
| Slackスレッドのリンク | スクリーンショット |

**個人情報はSlackスレッド側に置いたまま、IssueからはURLで参照する。** 類似案件の判定は症状と原因で行うので、これで機能は損なわれません。

全文をIssueに残したい場合は、記録先を非公開リポジトリに変えること。その場合は本スキル内の `BENieBlack/NexusLaravelMobileGameServer` を置き換える。

### 調査は読み取りのみ

DBに対して `UPDATE` / `DELETE` / `INSERT` を実行しない。データ復旧が必要な場合も、このスキルの範囲は「原因特定とIssue化」まで。復旧は [docs/trx_point_in_time_recovery.md](../../../docs/trx_point_in_time_recovery.md) の手順に従って別途行う。

## 手順

### 1. 入力を確認する

必要なもの:

- Slackスレッドの**URL**（Issueに残す）
- Slackスレッドの**本文**（貼り付けてもらう）
- 分かれば: プレイヤーID、発生日時、アプリバージョン、端末

本文が無ければ依頼して止まる。プレイヤーIDと発生日時が無い場合も、症状の類型調査までは進められるので、そこまでやってから不足を伝える。

### 2. 過去の類似案件を先に探す

**調査を始める前に必ずやること。** 同じ原因が既に判明していれば調査は不要になる。

```bash
# 症状のキーワードで全期間から検索する（クローズ済みも含める）
gh issue list --repo BENieBlack/NexusLaravelMobileGameServer \
  --label cs --state all --search "<キーワード>" --limit 20

# 症状カテゴリのラベルで絞る
gh issue list --repo BENieBlack/NexusLaravelMobileGameServer \
  --label cs --label "cs:login-bonus" --state all --limit 20
```

キーワードは症状の語（「ログインボーナス」「受け取れない」「重複」など）で複数回試す。1語で当たらなくても諦めない。

ヒットしたら本文を読み、**同一原因か別物かを判定して報告する。** 同一なら新規Issueを作らず既存Issueにコメントを追加する。

### 3. シャードを特定する

**プレイヤー個別の調査では最初にこれをやる。** trx系・log系はシャーディングされており、間違ったシャードを見ると「データが無い」という誤った結論になります。

```sql
-- sys DB
SELECT p.sys_player_id, n.node_no, n.node_name, n.status
FROM sys_sharding_node_player p
JOIN sys_sharding_node n ON n.id = p.sys_sharding_node_id
WHERE p.sys_player_id = <プレイヤーID>;
```

`node_no` が `trx<N>` / `log<N>` に対応します。以降のクエリは必ずそのシャードに対して投げること。

```bash
docker exec db-trx1 mysql -uroot -proot -e "SELECT ... FROM \`nexus-local-trx1\`.trx_item WHERE ..."
```

### 4. 症状から調査先を決める

症状カテゴリごとの調査先（テーブル・パッケージ・既知の落とし穴）は
**[references/investigation-map.md](./references/investigation-map.md)** にまとめてある。該当する節を読んでから調べること。

### 5. 原因を確定する

**推測で終わらせない。** 「〜の可能性があります」で報告すると、CS側は何も返答できません。

確定の条件:

- 実データかログで裏が取れている（`log_trx_*` に該当する変更履歴があるか等）
- または、コードを読んで**その条件なら必ずそうなる**と示せる
- 再現手順が書ける

裏が取れなければ「未確定」と明示し、確定に何が必要か（本番ログの照会、追加ヒアリング項目）を書く。曖昧なまま断定しないこと。

### 6. Issueに記録する

初回のみラベルを作る（2回目以降は何もしない）:

```bash
bash .claude/skills/cs-support/scripts/ensure-labels.sh
```

本文は [references/issue-template.md](./references/issue-template.md) の見出し構成をそのまま使う。**見出しを変えないこと。** 次回以降の類似案件検索が見出し前提で効きます。

```bash
gh issue create --repo BENieBlack/NexusLaravelMobileGameServer \
  --title "[CS] <症状の要約>" \
  --label cs --label "cs:<カテゴリ>" \
  --body-file <本文ファイル>
```

タイトルは**症状**を書く（原因ではない）。次回の検索はCS側の言葉で行われるためです。

- 良い: `[CS] カムバックログインボーナスが受け取れない`
- 悪い: `[CS] ComeBackLoginBonusServiceの日付判定バグ`

### 7. Slackに返す内容をまとめる

Issueとは別に、CS担当がそのまま使える文面を出力する。技術用語を避け、次の3点だけ書く。

1. 何が起きていたか
2. ユーザーへの影響（データは戻るのか、いつ直るのか）
3. 現時点で回答できること／できないこと

## 落とし穴

- **シャード特定を飛ばす** — trx1を見て「データが無い」と結論するのが最頻出の誤り。手順3を必ず通す
- **類似案件検索を飛ばす** — 既知の不具合を1から調べ直すことになる。手順2は調査より前
- **`log_*` と `log_trx_*` の混同** — `log_gacha` 等はゲーム内行動ログ、`log_trx_*` はPITR用の変更履歴。「消えた」系は後者を見る
- **ゲーム内の日付をカレンダーの日付で考える** — 日跨ぎ判定は `DAY_START_TIME`（未設定時 `00:00:00`）基準。`ClockUtility::getGameDayStart()` を通した値で判断する
- **Issueに個人情報を書く** — 公開リポジトリ。手順6の前に必ず見直す
