# Wallet管理への切り替え手順

## 概要

`mst_item.is_wallet` を立てると、そのアイテムは `trx_item` ではなく
Wallet（`trx_wallet` + `trx_wallet_balance`）で残高として管理されます。
有効期限付きの取得単位と先入先出の消費は Wallet 側にしか無いため、
gold / coin / 各種ポイントのように「残高」として扱いたいものはここへ寄せます。

リリース前であればフラグを立てるだけで済みます。
このドキュメントが対象にするのは **リリース後**、
すでにプレイヤーが `trx_item` に残高を持っている状態での切り替えです。

## 切り替えると何が起きるか

振り分けは `App\Domain\Item\Services\ItemService` の1箇所に集約されています。
`addItem` / `consumeItem` / `findItemAmount` が `is_wallet` を見て
Wallet 側と `trx_item` 側に分岐します。

そのため **フラグが切り替わった瞬間から、それまで `trx_item` に積まれていた
残高は参照されなくなります**。行が消えるわけではなく、見に行く先が変わります。

| 時点 | 所持数の表示 | 消費 | 新規付与 |
|---|---|---|---|
| フラグ反映前 | `trx_item` の値 | 通常どおり | `trx_item` |
| フラグ反映後〜移行コマンド前 | **0**（`trx_item` の行は無傷で残る） | **`InsufficientBalanceException`** | Wallet |
| 移行コマンド後 | 旧残高 + 移行中に付与されたぶん | 通常どおり | Wallet |

移行コマンドは既存の Wallet 残高に **足し込む**（上書きしない）ため、
上の「反映後〜コマンド前」に配られたぶんは失われません。
たとえば `trx_item` に 500 ある状態でフラグを反映し、
その間に 30 付与してからコマンドを流すと、最終的な残高は 530 になります。

つまりデータは失われませんが、**間の時間はプレイヤーから残高が消えて見え、
消費が例外で落ちます**。この窓を開けたまま運用してはいけません。

## マスターキャッシュに注意

`mst_item` は Redis に TTL 3600 秒でキャッシュされます
（`Nexus\Core\Repositories\Mst\_BaseMstRepository::$cacheTtl`）。
`mst` DB を更新しただけでは、キーが切れるまで最大1時間は
`is_wallet=false` のまま動き続けます。

**移行コマンド自身も同じキャッシュを読みます。**
マスター配信直後にコマンドを流すと、対象0件と判定して

```
Wallet管理のアイテムが見つかりませんでした
```

と表示し、**終了コード0で成功したように終わります**。
流したつもりで1件も移っていない状態になるため、
必ずキャッシュを消してからコマンドを実行してください。

キャッシュキーは `mst:{テーブル名}:all` です（`mst_item` なら `mst:mst_item:all`）。
現状これを消す artisan コマンドは無いため、tinker から実行します。

## 手順

移行コマンドは稼働中の実行を想定していません（後述）。
**メンテナンスでアクセスを止めてから実施してください。**

### 1. メンテナンスに入る

プレイヤーからのリクエストを止めます。
`trx_item` と `trx_wallet` の両方に書き込みが走らない状態にします。

### 2. マスターデータを配信する

対象アイテムの `is_wallet` を `true` にして `mst` DB へ反映します。

```sql
UPDATE mst_item SET is_wallet = true WHERE id IN ('gold', 'coin');
```

### 3. マスターキャッシュを消す

```bash
docker compose exec api-php php artisan tinker --execute="\Illuminate\Support\Facades\Cache::store('redis')->forget('mst:mst_item:all');"
```

これを飛ばすと、次のコマンドが対象0件で成功終了します。

`_BaseMstRepository::clearAllCaches()` でも消えますが、こちらは
Redis キャッシュストア全体を `flush()` します。`CACHE_STORE=redis` のため
他のマスターテーブルとアプリケーションキャッシュも巻き添えで消えます
（セッションは `SESSION_DRIVER=database` なので影響しません）。
消えたぶんは次のアクセスで DB から読み直されるだけですが、
本番では上記のキー単位の削除を使ってください。

### 4. 移行内容を確認する（dry-run）

```bash
docker compose exec api-php php artisan wallet:migrate-items --dry-run
```

```
[DRY RUN モード] 実際には移しません
対象アイテム: gold, coin
trx1: 12034 件
trx2: 11987 件
移行: 24021 件（合計 48291503）
```

`対象アイテム` に狙ったIDが並んでいることを確認します。
ここが空、または「Wallet管理のアイテムが見つかりませんでした」と出る場合は
ステップ3のキャッシュ削除ができていません。

### 5. 移行する

```bash
docker compose exec api-php php artisan wallet:migrate-items
```

特定のアイテムだけ移す場合は `--item` を付けます。

```bash
docker compose exec api-php php artisan wallet:migrate-items --item=gold
```

コマンドは `config('database.pitr.active_trx_connections')` の全シャードを走査し、
1行ごとに同一シャード内のトランザクションで次を行います。

1. `trx_wallet` に加算（行が無ければ作成）
2. `trx_wallet_balance` に無償ぶん・有償ぶんを1行ずつ挿入
3. 移し終えた `trx_item` の行を物理削除

`trx_item` に有効期限は無いため、移した残高は `expire_at = null`（無期限）で入ります。
有償・無償の内訳はそのまま保たれます。

### 6. 結果を確認する

`trx_item` に対象アイテムの行が残っていないこと、
`trx_wallet` の合計が移行前の `trx_item` の合計と一致することを確認します。

```sql
-- 各シャードで実行。0件になっているはず
SELECT COUNT(*) FROM trx_item WHERE mst_item_id IN ('gold', 'coin') AND is_delete = false;

-- 移行前に控えた合計と一致するはず
SELECT mst_item_id, SUM(free_amount + paid_amount) FROM trx_wallet
WHERE mst_item_id IN ('gold', 'coin') GROUP BY mst_item_id;
```

### 7. メンテナンスを解放する

## 制約と既知の注意点

切り替えを計画する前に把握しておく必要があるものです。

### 稼働中に流してはいけない

コマンドは対象行を `->get()` でトランザクション**外**に読み出し、
その値を使ってトランザクション内で Wallet へ書き、
`WHERE sys_player_id AND mst_item_id` で `trx_item` を削除します。
読み出しから削除までの間に同じ行が増減すると、その差分が失われるか二重になります。
メンテナンス中に実行してください。

### 論理削除済みの行は移らない

`is_delete = true` の `trx_item` は対象外です（意図した挙動）。
移行後も `trx_item` に残り続けます。

### メモリ

シャードごとに対象行を全件メモリへ読み込みます。
対象行数が多い場合は、`--item` でアイテムを分けて複数回に分けて実行してください。

### 失敗しても終了コードが0になる

`--item` に Wallet 管理でないIDを渡した場合、エラーを表示しますが
終了コードは 0 です。バッチのジョブ管理から失敗を検知できないため、
出力内容を確認してください。

### 元に戻せない

`is_wallet` を `false` に戻すと、今度は Wallet 側の残高が参照されなくなり、
同じ「残高が0に見える」状態になります。
Wallet から `trx_item` へ戻すコマンドはありません。
切り替えは一方向の操作として扱ってください。

## 関連

- `api/app/Domain/Item/Services/ItemService.php` - 振り分けの分岐点
- `api/app/Console/Commands/Wallet/MigrateItemsCommand.php` - 移行コマンド
- `api/app/Repositories/Mst/MstItemRepository.php` - `isWalletManaged()` / `selectWalletManaged()`
- `api/tests/Feature/Domain/Item/WalletManagedItemTest.php` - 振り分けと移行の挙動を固定したテスト
