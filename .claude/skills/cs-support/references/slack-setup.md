# Slackスレッドを自動取得できるようにする

現状、このプロジェクトには Slack 連携が**一切ありません**。

確認済みの状態（2026-08-19時点）:

```
claude mcp list        → Google Drive / Google Calendar / Gmail / clickup のみ
env | grep -i slack    → なし
which slack            → なし
~/.slack*              → なし
```

そのため `cs-support` スキルはスレッド本文の貼り付けを前提にしています。以下を設定すればURLだけで完結します。

## 必要なもの

Slackアプリのトークン。`nexus-cs` チャンネルの読み取りに必要なスコープ:

| スコープ | 用途 |
|---|---|
| `channels:history` | パブリックチャンネルのメッセージ取得 |
| `groups:history` | プライベートチャンネルの場合はこちら |
| `users:read` | 投稿者名の解決 |

`nexus-cs` が非公開チャンネルなら、アプリをそのチャンネルに招待する必要があります。

## 設定手順

1. https://api.slack.com/apps でアプリを作成し、上記スコープを付与してワークスペースにインストールする
2. Bot User OAuth Token（`xoxb-` で始まる）を取得する
3. MCPサーバーを登録する

```bash
claude mcp add slack --scope project \
  --env SLACK_BOT_TOKEN=xoxb-... \
  --env SLACK_TEAM_ID=T... \
  -- npx -y @modelcontextprotocol/server-slack
```

`--scope project` にすると `.mcp.json` がリポジトリに作られ、チーム全員が同じ設定を使えます。
**トークンを `.mcp.json` に直書きしないこと。** 環境変数参照にするか、`--scope local` で個人設定に置く。

4. `claude mcp list` で接続を確認する
5. Claude Code を再起動する（MCPサーバーは起動時に読み込まれる）

## 設定後にスキル側で直すこと

`SKILL.md` の以下を書き換える。

- 「前提」の「Slackは自動では読めない」節を削除する
- 手順1を「スレッドURLからメッセージを取得する」に変える

URLの形式は次の通りで、スレッドの取得には `channel` と `ts` が必要です。

```
https://<workspace>.slack.com/archives/<channel_id>/p<ts_without_dot>
                                       ^^^^^^^^^^^^   ^^^^^^^^^^^^^^^^
                                       channel        タイムスタンプ
                                                      p1723456789123456
                                                      → 1723456789.123456
```

## 設定しない場合

貼り付け運用のままで問題ありません。**個人情報をIssueに書かない**という制約はどちらでも同じで、むしろ手動で貼る運用のほうが、何がClaudeに渡ったか把握しやすいという利点があります。
