# 規約の HTML の運用

規約（利用規約・プライバシーポリシーなど）の本文を HTML で置き、クライアントが取得して画面に描画する。この文書は、置き場所・ファイル名・使えるタグ・登録と改版の手順を定める。

## 置き場所

- `api/public/legal/`（git 管理）。api-nginx が静的ファイルとしてそのまま配信する（例: `http://localhost:8090/legal/terms-of-service-1.0.0.html`、Content-Type は text/html）。
- **本番では CDN などの別の配信先に置く想定**。その場合は `sys_agreement.url` に絶対 URL を入れる（下記）。`public/legal/` は開発・動作確認用。
- 今ある文面は**すべてサンプル**（冒頭に「これは開発・動作確認用のサンプルです」と明記）。実際の規約の文面ではない。

## ファイル名の規則

`<種類>-<版>.html`。**版をファイル名に含める**（例: `terms-of-service-1.0.0.html`、`privacy-policy-1.0.0.html`）。動画撮影用のシナリオだけが使うものは `rec-sample-*.html`。英数字・`.`・`-` だけを使う。

## 使えるタグ

クライアント（Godot）が描画できる範囲に限る。これ以外は描画されない・崩れるため使わない。

| タグ | 用途 |
| --- | --- |
| `h1` `h2` `h3` | 見出し |
| `p` | 段落 |
| `ul` `ol` `li` | リスト |
| `strong` `em` | 太字・強調 |
| `a`（`href` のみ） | リンク |
| `br` `hr` | 改行・区切り線 |

骨組みとして `html`（`lang`）・`head`・`meta charset="utf-8"`・`title`・`body` は置く（`<meta charset="utf-8">` は必須）。`script`・`style`（タグも属性も）・`img`・`table`・`div` などのレイアウト用の要素は使わない。見た目はクライアントが決める。許可タグの検査は `api/tests/Feature/Recording/RecordingTermsHtmlTest.php` が行う。

## sys_agreement への登録

`sys_agreement.url`（varchar 500）に本文の場所を入れる。`GET /agreement/pending` の応答の `url` にそのまま入る。クライアントは次の両方を扱う。

- 相対パス（`/` で始まる。例 `/legal/terms-of-service-1.0.0.html`）: API の origin（例 `http://localhost:8090`）に付けて取得する
- 絶対 URL（例 `https://cdn.example.com/legal/terms-of-service-1.0.0.html`）: そのまま取得する

動画撮影用のシーダーの対応は `api/database/seeders/Recording/README.md` の「規約の HTML」の表。

## 新しい版を出す手順

1. 新しい版の HTML を足す（例 `terms-of-service-1.1.0.html`）
2. `sys_agreement` に新しい `version` の行を足す（`url` に 1 の相対パスまたは絶対 URL、`published_at` に公開日時）

**既存の版の HTML は書き換えない**。プレイヤーが同意したのは、その版の本文だから。後から本文を変えると、同意した内容と表示される内容がずれる。誤字などの修正でも、新しい版として出す。
