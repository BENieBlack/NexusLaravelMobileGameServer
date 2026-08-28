# ガチャシステム設計

## 概要

ガチャシステムは、プレイヤーがダイヤモンド、有償ダイヤモンド、またはアイテム（チケット）を消費して、ランダムな景品を獲得する機能です。

## テーブル設計

### マスターテーブル (mst)

#### mst_gacha
ガチャの基本情報を管理

| カラム名 | 型 | 説明 |
|---------|-----|------|
| id | string | ガチャID（主キー） |
| sort_desc | int | 表示順序（降順） |
| is_active | bool | 有効フラグ |
| start_at | datetime | 開始日時（NULL=常時表示） |
| end_at | datetime | 終了日時（NULL=無期限） |
| daily_limit | int | 1日の実行回数制限（0=無制限） |
| has_step_up | bool | ステップアップガチャか |

#### mst_gacha__l10n
ガチャの多言語情報

| カラム名 | 型 | 説明 |
|---------|-----|------|
| mst_gacha_id | string | ガチャID |
| language | enum | 言語コード |
| title | string | ガチャタイトル |
| description | text | 説明 |

#### mst_gacha_cost
ガチャコスト設定（1連、10連ごと）

| カラム名 | 型 | 説明 |
|---------|-----|------|
| mst_gacha_id | string | ガチャID |
| draw_count | int | 実行回数（1, 10など） |
| cost_type | enum | コストタイプ（diamond/paid_diamond/item） |
| cost_mst_id | string | コストID（itemの場合はmst_item_id） |
| cost_amount | int | コスト量 |
| is_active | bool | 有効フラグ |

**主キー**: (mst_gacha_id, draw_count, cost_type, cost_mst_id)

**例**:
```
gacha_001, 1, diamond, null, 150      # 1連: 無償ダイヤ150個
gacha_001, 1, paid_diamond, null, 150 # 1連: 有償ダイヤ150個
gacha_001, 10, diamond, null, 1500    # 10連: 無償ダイヤ1500個
gacha_001, 1, item, ticket_001, 1     # 1連: チケット1枚
```

#### mst_gacha_rarity_rate
レアリティ別排出率設定

| カラム名 | 型 | 説明 |
|---------|-----|------|
| mst_gacha_id | string | ガチャID |
| rarity | tinyint | レアリティ（1~5、5が最高レア） |
| rate | int | 排出率（10000分率、例：500=5%） |

**主キー**: (mst_gacha_id, rarity)

**排出率の合計は10000である必要がある**

**例**:
```
gacha_001, 5, 300   # レア5: 3%
gacha_001, 4, 1200  # レア4: 12%
gacha_001, 3, 2500  # レア3: 25%
gacha_001, 2, 3000  # レア2: 30%
gacha_001, 1, 3000  # レア1: 30%
合計: 10000 (100%)
```

#### mst_gacha_prize
ガチャ景品マスター（レアリティごとの個別オブジェクト）

| カラム名 | 型 | 説明 |
|---------|-----|------|
| id | bigint | 景品ID（主キー） |
| mst_gacha_id | string | ガチャID |
| rarity | tinyint | レアリティ（1~5） |
| content_type | enum | コンテンツタイプ（item/unit/equipment） |
| content_mst_id | string | コンテンツID |
| amount | int | 獲得数量 |
| weight | int | 重み（同レアリティ内での排出率） |
| is_pickup | bool | ピックアップ対象か |
| is_active | bool | 有効フラグ |

**重み（weight）の仕組み**:
- 同じレアリティ内の全景品のweightの合計に対する割合で排出
- 例: レア5の景品が3つ（weight: 10, 20, 30）の場合
  - 景品A: 10/60 = 16.67%
  - 景品B: 20/60 = 33.33%
  - 景品C: 30/60 = 50%

### トランザクションテーブル (trx)

#### trx_gacha_history
ガチャ実行履歴

| カラム名 | 型 | 説明 |
|---------|-----|------|
| id | bigint | 履歴ID（主キー） |
| sys_player_id | bigint | プレイヤーID |
| mst_gacha_id | string | ガチャID |
| draw_count | int | 実行回数（1連、10連など） |
| cost_type | enum | 使用したコストタイプ |
| cost_mst_id | string | 使用したコストID |
| cost_amount | int | 使用したコスト量 |
| prizes | json | 獲得した景品リスト |
| created_at | datetime | 実行日時 |

**prizesのJSON構造**:
```json
[
  {
    "rarity": 5,
    "content_type": "unit",
    "content_mst_id": "unit_001",
    "amount": 1,
    "is_pickup": true
  },
  {
    "rarity": 3,
    "content_type": "item",
    "content_mst_id": "item_002",
    "amount": 10,
    "is_pickup": false
  }
]
```

#### trx_gacha_daily_count
ガチャ日次実行カウント（daily_limit制限用）

| カラム名 | 型 | 説明 |
|---------|-----|------|
| sys_player_id | bigint | プレイヤーID |
| mst_gacha_id | string | ガチャID |
| date | date | 日付（YYYY-MM-DD） |
| count | int | 実行回数 |

**主キー**: (sys_player_id, mst_gacha_id, date)

---

## ガチャ抽選ロジック

### 2段階抽選方式

#### 第1段階: レアリティ抽選
1. `mst_gacha_rarity_rate`から排出率を取得
2. 1~10000の乱数を生成
3. 排出率の累積値でレアリティを決定

**例**:
```
乱数: 1234

レア5: 0~300      (3%)
レア4: 301~1500   (12%)  ← 1234はこの範囲
レア3: 1501~4000  (25%)
レア2: 4001~7000  (30%)
レア1: 7001~10000 (30%)

結果: レア4
```

#### 第2段階: 個別オブジェクト抽選
1. 決定したレアリティの`mst_gacha_prize`を取得（is_active=true）
2. 全景品のweightの合計を計算
3. 1~合計weightの乱数を生成
4. weightの累積値で景品を決定

**例**:
```
レア4の景品:
- 景品A: weight=10
- 景品B: weight=20
- 景品C: weight=30
合計: 60

乱数: 35

景品A: 1~10
景品B: 11~30
景品C: 31~60  ← 35はこの範囲

結果: 景品C
```

---

## 機能フロー

### ガチャ実行フロー

```
1. バリデーション
   - ガチャが有効期間内か（start_at <= now <= end_at）
   - ガチャが有効か（is_active=true）
   - 日次制限内か（daily_limit）
   - コストを所持しているか

2. コスト消費
   - diamond: trx_player_diamond減算
   - paid_diamond: trx_player_paid_diamond減算
   - item: trx_item減算

3. 抽選処理（draw_count回繰り返し）
   - レアリティ抽選
   - 個別オブジェクト抽選
   - 結果を配列に追加

4. 景品付与
   - DeliverySystemを使用して景品を付与
   - 重複ユニット/装備は自動変換

5. 履歴記録
   - trx_gacha_historyに記録
   - trx_gacha_daily_countを更新

6. レスポンス返却
   - 獲得景品リスト
   - 演出用情報（レアリティ、ピックアップフラグなど）
```

---

## 実装クラス構成

### Domain層

```
app/Domain/Gacha/
├── Constants/
│   └── GachaConst.php              # 定数定義
├── DTOs/
│   ├── GachaCost.php               # コスト情報
│   ├── GachaPrize.php              # 景品情報
│   ├── GachaDrawRequest.php        # ガチャ実行リクエスト
│   └── GachaDrawResult.php         # ガチャ実行結果
├── Services/
│   ├── GachaDrawService.php        # 抽選ロジック
│   ├── GachaValidationService.php  # バリデーション
│   └── GachaCostService.php        # コスト処理
└── UseCases/
    ├── DrawGachaUseCase.php        # ガチャ実行
    └── GetGachaListUseCase.php     # ガチャ一覧取得
```

### Repository層

```
app/Repositories/
├── Mst/
│   ├── MstGachaRepository.php
│   ├── MstGachaCostRepository.php
│   ├── MstGachaRarityRateRepository.php
│   └── MstGachaPrizeRepository.php
└── Trx/
    ├── TrxGachaHistoryRepository.php
    └── TrxGachaDailyCountRepository.php
```

---

## API設計

### GET /api/gacha
ガチャ一覧取得

**レスポンス**:
```json
{
  "gachas": [
    {
      "id": "gacha_001",
      "title": "プレミアムガチャ",
      "description": "最高レアが当たる！",
      "start_at": "2026-04-01T00:00:00Z",
      "end_at": "2026-04-30T23:59:59Z",
      "daily_limit": 5,
      "remaining_count": 3,
      "costs": [
        {
          "draw_count": 1,
          "cost_type": "diamond",
          "cost_amount": 150
        },
        {
          "draw_count": 10,
          "cost_type": "diamond",
          "cost_amount": 1500
        }
      ]
    }
  ]
}
```

### POST /api/gacha/draw
ガチャ実行

**リクエスト**:
```json
{
  "gacha_id": "gacha_001",
  "draw_count": 10,
  "cost_type": "diamond"
}
```

**レスポンス**:
```json
{
  "prizes": [
    {
      "rarity": 5,
      "content_type": "unit",
      "content_mst_id": "unit_001",
      "amount": 1,
      "is_pickup": true,
      "is_new": true
    },
    {
      "rarity": 4,
      "content_type": "equipment",
      "content_mst_id": "equip_002",
      "amount": 1,
      "is_pickup": false,
      "is_new": false
    }
  ],
  "cost": {
    "cost_type": "diamond",
    "cost_amount": 1500
  }
}
```

---

## 拡張機能（将来実装）

### ステップアップガチャ
- `has_step_up`フラグがtrueの場合
- 実行回数に応じて排出率やコストが変化
- `mst_gacha_step`テーブルで管理

### 天井機能
- 一定回数実行で必ず最高レアが出る
- `mst_gacha_ceiling`テーブルで管理
- `trx_gacha_ceiling_progress`でプレイヤーごとの進捗管理

### ピックアップ率アップ
- `is_pickup=true`の景品の排出率を上げる
- 通常weightに加えてbonus_weightを設定

---

## 注意事項

### セキュリティ
- 抽選処理は必ずサーバー側で実行
- クライアントには結果のみ返す
- シード値は予測不可能な値を使用

### パフォーマンス
- マスターデータはキャッシュする
- 10連などまとめて実行時はバッチ処理
- 履歴テーブルは定期的にパーティショニング

### 法規制対応
- 排出率の表示（景品表示法）
- 有償ダイヤの使用履歴保持
- コンプリートガチャ（コンプガチャ）の禁止

---

## データ例

### ガチャ設定例

**プレミアムガチャ（期間限定）**
```sql
-- mst_gacha
INSERT INTO mst_gacha VALUES (
  'premium_gacha_202604',
  100,
  true,
  '2026-04-01 00:00:00',
  '2026-04-30 23:59:59',
  0,
  false
);

-- コスト設定
INSERT INTO mst_gacha_cost VALUES
  ('premium_gacha_202604', 1, 'diamond', null, 150, true),
  ('premium_gacha_202604', 10, 'diamond', null, 1500, true);

-- レアリティ排出率
INSERT INTO mst_gacha_rarity_rate VALUES
  ('premium_gacha_202604', 5, 300),   -- 3%
  ('premium_gacha_202604', 4, 1200),  -- 12%
  ('premium_gacha_202604', 3, 2500),  -- 25%
  ('premium_gacha_202604', 2, 3000),  -- 30%
  ('premium_gacha_202604', 1, 3000);  -- 30%

-- 景品（レア5）
INSERT INTO mst_gacha_prize VALUES
  (1, 'premium_gacha_202604', 5, 'unit', 'unit_ssr_001', 1, 10, true, true),
  (2, 'premium_gacha_202604', 5, 'unit', 'unit_ssr_002', 1, 10, true, true),
  (3, 'premium_gacha_202604', 5, 'equipment', 'equip_ur_001', 1, 5, false, true);
```

**チケットガチャ（常設・日次制限あり）**
```sql
-- mst_gacha
INSERT INTO mst_gacha VALUES (
  'ticket_gacha_daily',
  50,
  true,
  null,
  null,
  1,  -- 1日1回
  false
);

-- コスト設定
INSERT INTO mst_gacha_cost VALUES
  ('ticket_gacha_daily', 1, 'item', 'gacha_ticket_001', 1, true);
```
