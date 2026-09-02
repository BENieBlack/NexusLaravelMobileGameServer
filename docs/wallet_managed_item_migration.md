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

遅延移行が無ければ、移し終えるまでの間は次のように見えます。

| 時点 | 所持数の表示 | 消費 | 新規付与 |
|---|---|---|---|
| フラグ反映前 | `trx_item` の値 | 通常どおり | `trx_item` |
| フラグ反映後〜移行コマンド前 | **0**（`trx_item` の行は無傷で残る） | **`InsufficientBalanceException`** | Wallet |
| 移行コマンド後 | 旧残高 + 移行中に付与されたぶん | 通常どおり | Wallet |

移行コマンドは既存の Wallet 残高に **足し込む**（上書きしない）ため、
上の「反映後〜コマンド前」に配られたぶんは失われません。
たとえば `trx_item` に 500 ある状態でフラグを反映し、
その間に 30 付与してからコマンドを流すと、最終的な残高は 530 になります。

この窓は **遅延移行で塞いであります**。Wallet管理のアイテムを
読み書きする直前に `WalletItemMigrator` が `trx_item` を覗き、
残高が残っていればその場で Wallet へ移します。

そのため実際の挙動は次のようになります。

| 時点 | 所持数の表示 | 消費 | 新規付与 |
|---|---|---|---|
| フラグ反映前 | `trx_item` の値 | 通常どおり | `trx_item` |
| フラグ反映後（初回アクセス） | 旧残高（その場で移る） | 通常どおり | Wallet |
| 以降 | Wallet の値 | 通常どおり | Wallet |

**メンテナンスは要りません。** 一括移行コマンドを流す前でも、
プレイヤーから見える残高は変わりません。

遅延移行は設定で切れます。切った場合はコマンドだけが移す手段になり、
上の表の「移し終えるまで残高が消えて見える」状態が戻ります。
選び方は後述します。

## マスターキャッシュに注意

`mst_item` は Redis に TTL 3600 秒でキャッシュされます
（`Nexus\Core\Repositories\Mst\_BaseMstRepository::$cacheTtl`）。
`mst` DB を更新しただけでは、キーが切れるまで最大1時間は
**ゲーム側は `is_wallet=false` のまま動き続けます**。

一方、移行コマンドは `mst` DB を直接読みます。キャッシュ越しに読むと
`is_wallet` を立てた直後は false に見えて、1件も移さないまま
成功扱いで終わってしまうためです。

コマンドを使う場合は、**コマンド → キャッシュ削除** の順にしてください。
逆順でも遅延移行が残高を拾うため実害はありませんが、
コマンドが移すはずだった行を先にプレイヤー側が移すことになり、
コマンドの件数が実態と合わなくなります。

キャッシュキーは `mst:{テーブル名}:all` です（`mst_item` なら `mst:mst_item:all`）。
現状これを消す artisan コマンドは無いため、tinker から実行します。

## 運用の選び方

切り替え方は2通りあり、`wallet.lazy_migration`（環境変数 `WALLET_LAZY_MIGRATION`）で選びます。

| | 遅延移行あり（既定） | コマンドのみ |
|---|---|---|
| 設定 | `WALLET_LAZY_MIGRATION=true` | `WALLET_LAZY_MIGRATION=false` |
| メンテナンス | 不要 | **必要** |
| 手順 | マスターを配信するだけ | 止める → 配信 → コマンド → 開放 |
| `trx_item` の残り方 | 触られた分から順に消える | コマンドで一度に消える |
| 読み取り時の書き込み | 起きる（初回アクセス時） | 起きない |

読み取りの中で書き込みが走るのを避けたい場合や、切り替えの時刻を
はっきり揃えたい場合は、コマンドのみの運用を選んでください。

どちらの場合も、移す処理そのものは同じ実装（`WalletItemMigrator`）を通ります。

## 手順A: 遅延移行あり（既定）

切り替えそのものは **マスターを配信するだけ** で終わります。
一括移行コマンドは必須ではありません。

触られていないプレイヤーの `trx_item` はしばらく残ります。
集計や分析でその行が邪魔になる場合や、まとめて片付けたい場合に
コマンドを使ってください。**コマンドは稼働中の実行を想定していません**（後述）。

### 1. マスターデータを配信する

対象アイテムの `is_wallet` を `true` にして `mst` DB へ反映します。

```sql
UPDATE mst_item SET is_wallet = true WHERE id IN ('gold', 'coin');
```

### 2. 移行内容を確認する（dry-run、任意）

```bash
docker compose exec api-php php artisan wallet:migrate-items --dry-run
```

```
[DRY RUN モード] 実際には移しません
対象アイテム: coin, gold
trx1: 12034 件
trx2: 11987 件
移行: 24021 件（合計 48291503）
```

`対象アイテム` に狙ったIDが並んでいることを確認します。

### 3. 移行する（任意）

```bash
docker compose exec api-php php artisan wallet:migrate-items
```

特定のアイテムだけ移す場合は `--item` を付けます。
マスターに無いIDや `is_wallet` が立っていないIDを渡すと、
エラーを表示して終了コード1で終わります。

```bash
docker compose exec api-php php artisan wallet:migrate-items --item=gold
```

コマンドは `config('database.pitr.active_trx_connections')` の全シャードを走査し、
主キー `(sys_player_id, mst_item_id)` を辿って `--chunk` 件（既定1000）ずつ読み出し、
1行ごとに同一シャード内のトランザクションで次を行います。

1. `trx_item` の行を `FOR UPDATE` で読み直す（消えていればスキップ）
2. `trx_wallet` に加算（行が無ければ作成）
3. `trx_wallet_balance` に無償ぶん・有償ぶんを1行ずつ挿入
4. 移し終えた `trx_item` の行を物理削除

`trx_item` に有効期限は無いため、移した残高は `expire_at = null`（無期限）で入ります。
有償・無償の内訳はそのまま保たれます。

読み直した時点で行が消えていた、または論理削除されていた場合はスキップし、
最後に件数を警告として出します。

```
移行中に変化した行をスキップしました: 3 件
```

メンテナンス中であればここは0件になるはずです。0件でない場合は
アクセスが止まっていないため、残高が合っているか確認してください。

### 4. マスターキャッシュを消す

ここで初めてゲーム側が `is_wallet` を見るようになります。

```bash
docker compose exec api-php php artisan tinker --execute="\Illuminate\Support\Facades\Cache::store('redis')->forget('mst:mst_item:all');"
```

`_BaseMstRepository::clearAllCaches()` でも消えますが、こちらは
Redis キャッシュストア全体を `flush()` します。`CACHE_STORE=redis` のため
他のマスターテーブルとアプリケーションキャッシュも巻き添えで消えます
（セッションは `SESSION_DRIVER=database` なので影響しません）。
消えたぶんは次のアクセスで DB から読み直されるだけですが、
本番では上記のキー単位の削除を使ってください。

### 5. 結果を確認する

コマンドを流した場合のみ確認します。

`trx_item` に対象アイテムの行が残っていないこと、
`trx_wallet` の合計が移行前の `trx_item` の合計と一致することを確認します。

```sql
-- 各シャードで実行。0件になっているはず
SELECT COUNT(*) FROM trx_item WHERE mst_item_id IN ('gold', 'coin') AND is_delete = false;

-- 移行前に控えた合計と一致するはず
SELECT mst_item_id, SUM(free_amount + paid_amount) FROM trx_wallet
WHERE mst_item_id IN ('gold', 'coin') GROUP BY mst_item_id;
```

## 手順B: コマンドのみ（`WALLET_LAZY_MIGRATION=false`）

遅延移行を切っている場合、移す手段はコマンドだけになります。
マスターを配信してからコマンドが終わるまでの間、
プレイヤーからは残高が0に見え、消費が `InsufficientBalanceException` で落ちます。
**メンテナンスでアクセスを止めてから実施してください。**

1. メンテナンスに入る（`trx_item` と `trx_wallet` の両方に書き込みが走らない状態にする）
2. マスターデータを配信する（手順Aの1と同じ）
3. `wallet:migrate-items --dry-run` で対象を確認する
4. `wallet:migrate-items` を流す
5. マスターキャッシュを消す（手順Aの4と同じ）
6. 結果を確認する（手順Aの5と同じ）
7. メンテナンスを解放する

コマンドは `mst` DB を直接読むため、5を先に行う必要はありません。
ゲーム側が `is_wallet` を見るようになるのは5の後です。

## 制約と既知の注意点

切り替えを計画する前に把握しておく必要があるものです。

### 稼働中に流してはいけない

金額はトランザクション内で `FOR UPDATE` を取って読み直すため、
コマンド単体で残高を取りこぼすことはありません。
ただし、ゲーム側は UnitOfWork でリクエスト終了時にまとめて書き込みます。
読み取り時点では存在した `trx_item` の行がコマンドに消された後で
`UPDATE` が走ると、その更新は行に当たらず捨てられます。
メンテナンス中に実行してください。

スキップされた行があると警告が出ます。0件でない場合はアクセスが
止まっていないため、対象プレイヤーの残高を確認してください。

なお遅延移行はこの制約を受けません。プレイヤー自身のリクエストの中で
移すため、同じ行を別の経路が同時に触ることがないためです。

### 論理削除済みの行は移らない

`is_delete = true` の `trx_item` は対象外です（意図した挙動）。
移行後も `trx_item` に残り続けます。

### メモリ

`--chunk` 件ずつ読み出すため、対象行が多くてもメモリは一定です。
既定は1000件で、行数が多い場合に落ちるようなら小さくしてください。

### 元に戻せない

`is_wallet` を `false` に戻すと、今度は Wallet 側の残高が参照されなくなり、
同じ「残高が0に見える」状態になります。
Wallet から `trx_item` へ戻すコマンドはありません。
切り替えは一方向の操作として扱ってください。

## 関連

- `api/app/Domain/Item/Services/ItemService.php` - 振り分けの分岐点
- `api/app/Domain/Item/Support/WalletItemMigrator.php` - 遅延移行。移す処理の実体。
  一括移行コマンドもここを呼ぶ
- `api/app/Domain/Item/Services/ItemGranterAdapter.php` - 配送から振り分けへ入る経路。
  メールボックスやガチャの中身は `ItemDeliveryHandler` を通るため、
  ここを経由しないと `is_wallet` の判定を通らない
- `api/app/Console/Commands/Wallet/MigrateItemsCommand.php` - 移行コマンド
- `api/app/Repositories/Mst/MstItemRepository.php` - `isWalletManaged()` / `selectWalletManaged()`
- `api/tests/Feature/Domain/Item/WalletManagedItemTest.php` - 振り分けと移行の挙動を固定したテスト
