# メールボックスシステム 設計書

## 📋 既存システムの分析結果

### 現在の実装
- **mst_message**: メッセージマスター（タイトル・本文の多言語対応）
- **mst_mailbox**: メールボックスマスター
- **mst_mailbox_content**: 添付アイテム（Diamond, Item, Unit, Equipment）
- **trx_mailbox**: プレイヤーごとのメール受信箱
  - `is_opened`: 既読フラグ
  - `is_received`: 受取済みフラグ
  - `is_delete`: 論理削除フラグ

### 不足している機能
1. ❌ カテゴリ分類（システム、戦闘レポート、アライアンス、個人など）
2. ❌ 一括受取機能
3. ❌ 保護機能（重要なメールを削除から保護）
4. ❌ 有効期限・自動削除
5. ❌ 送信者情報
6. ❌ フィルタ・検索機能
7. ❌ 添付物の複数種類対応（現在は4種類のみ）

---

## 🎯 目指すメールボックスの特徴

### 1. カテゴリシステム
| カテゴリ | 説明 | 例 |
|---------|------|-----|
| **System** | システムメッセージ | メンテナンス、アップデート通知 |
| **Battle** | 戦闘レポート | PvP結果、防衛レポート |
| **Alliance** | アライアンス関連 | ギルド招待、寄付報酬 |
| **Friend** | フレンド関連 | フレンド申請、ギフト |
| **Trade** | 取引関連 | マーケット取引完了 |
| **Reward** | 報酬 | イベント報酬、ランキング報酬 |
| **Personal** | 個人メッセージ | プレイヤー間メッセージ |

### 2. 優先度・重要度
- **Normal**: 通常のメール
- **Important**: 重要なメール（オレンジアイコン）
- **Urgent**: 緊急メール（赤アイコン）

### 3. 添付アイテム
- 複数種類の添付物に対応
- 一括受取機能
- 受取済み・未受取の視覚的な区別

### 4. 保護機能
- ユーザーが重要なメールを保護
- 保護されたメールは自動削除されない

### 5. 有効期限
- メールごとに有効期限を設定
- 期限切れメールは自動削除
- 添付物がある場合は警告

### 6. 一括操作
- 一括既読
- 一括受取（添付物）
- 一括削除

---

## 📊 拡張DB設計

### mst_mailbox 拡張

```sql
ALTER TABLE mst_mailbox ADD COLUMN category ENUM(
    'System', 'Battle', 'Alliance', 'Friend', 
    'Trade', 'Reward', 'Personal'
) DEFAULT 'System' COMMENT 'メールカテゴリ';

ALTER TABLE mst_mailbox ADD COLUMN priority ENUM(
    'Normal', 'Important', 'Urgent'
) DEFAULT 'Normal' COMMENT '優先度';

ALTER TABLE mst_mailbox ADD COLUMN sender_type ENUM(
    'System', 'Player', 'Alliance', 'NPC'
) DEFAULT 'System' COMMENT '送信者タイプ';

ALTER TABLE mst_mailbox ADD COLUMN sender_id VARCHAR(255) NULL COMMENT '送信者ID';

ALTER TABLE mst_mailbox ADD COLUMN expires_in_days INT UNSIGNED DEFAULT 30 COMMENT '有効期限（日数）';

ALTER TABLE mst_mailbox ADD COLUMN icon_url VARCHAR(512) NULL COMMENT 'アイコン画像URL';

ALTER TABLE mst_mailbox ADD COLUMN is_bulk_distributable BOOLEAN DEFAULT FALSE COMMENT '一斉配信可能フラグ';
```

### trx_mailbox 拡張

```sql
ALTER TABLE trx_mailbox ADD COLUMN is_protected BOOLEAN DEFAULT FALSE COMMENT '保護フラグ';

ALTER TABLE trx_mailbox ADD COLUMN expires_at DATETIME NULL COMMENT '有効期限';

ALTER TABLE trx_mailbox ADD COLUMN read_at DATETIME NULL COMMENT '既読日時';

ALTER TABLE trx_mailbox ADD COLUMN received_at DATETIME NULL COMMENT '受取日時';

ALTER TABLE trx_mailbox ADD COLUMN sender_name VARCHAR(255) NULL COMMENT '送信者名（動的）';

ALTER TABLE trx_mailbox ADD COLUMN custom_params JSON NULL COMMENT 'カスタムパラメータ（プレースホルダー置換用）';

CREATE INDEX idx_expires_at ON trx_mailbox(sys_player_id, expires_at);
CREATE INDEX idx_category ON trx_mailbox(sys_player_id, is_delete) 
    INCLUDE (mst_mailbox_id);
```

### mst_mailbox_content 拡張

```sql
ALTER TABLE mst_mailbox_content MODIFY content_type ENUM(
    'Diamond',          -- ダイヤ
    'PaidDiamond',      -- 有償ダイヤ
    'Item',             -- アイテム
    'Unit',             -- ユニット
    'Equipment',        -- 装備
    'Gold',             -- ゴールド
    'Food',             -- 食料
    'Wood',             -- 木材
    'Stone',            -- 石材
    'Stamina',          -- スタミナ
    'Experience',       -- 経験値
    'AlliancePoints',   -- アライアンスポイント
    'Custom'            -- カスタムリソース
) COMMENT 'コンテンツタイプ';

ALTER TABLE mst_mailbox_content ADD COLUMN rarity ENUM(
    'C', 'UC', 'R', 'SR', 'SSR', 'UR'
) NULL COMMENT 'レアリティ（表示用）';

ALTER TABLE mst_mailbox_content ADD COLUMN is_highlight BOOLEAN DEFAULT FALSE COMMENT 'ハイライト表示';
```

---

## 🔧 新規機能の実装

### 1. カテゴリフィルタリング

**API Endpoint:**
```
GET /api/mailbox/list?category=Battle&unread_only=true
```

**Response:**
```json
{
  "categories": {
    "System": {"count": 5, "unread": 2},
    "Battle": {"count": 12, "unread": 8},
    "Reward": {"count": 3, "unread": 0}
  },
  "mails": [...]
}
```

### 2. 一括受取機能

**API Endpoint:**
```
POST /api/mailbox/receive_all
{
  "category": "Reward",       // オプション
  "mailbox_ids": [1, 2, 3],   // オプション（指定がなければ全て）
  "filter": {
    "has_attachments": true,
    "unread_only": false
  }
}
```

**Response:**
```json
{
  "received_count": 15,
  "rewards": {
    "Diamond": 1000,
    "Gold": 50000,
    "items": [
      {"item_id": "item_001", "amount": 10}
    ]
  },
  "errors": [
    {"mailbox_id": 123, "reason": "already_received"}
  ]
}
```

### 3. 保護機能

**API Endpoint:**
```
POST /api/mailbox/protect
{
  "mailbox_ids": [1, 2, 3],
  "is_protected": true
}
```

### 4. 自動削除バッチ

**Scheduled Job (毎日実行):**
```php
// 有効期限切れ + 保護されていない + 受取済みのメールを削除
TrxMailbox::where('expires_at', '<', now())
    ->where('is_protected', false)
    ->where('is_received', true)
    ->update(['is_delete' => true]);
```

### 5. テンプレート・プレースホルダー

**テンプレート例:**
```
{player_name}様、
{alliance_name}からの攻撃を受けました。
結果: {battle_result}
```

**動的置換:**
```json
{
  "custom_params": {
    "player_name": "Commander",
    "alliance_name": "Warriors",
    "battle_result": "Victory"
  }
}
```

---

## 📱 クライアント側UI設計

### メインビュー
```
┌────────────────────────────────┐
│  メールボックス        [設定]   │
├────────────────────────────────┤
│ [全て] [システム] [戦闘] ...    │ ← カテゴリタブ
├────────────────────────────────┤
│ [一括受取] [既読にする] [削除] │ ← 一括操作ボタン
├────────────────────────────────┤
│ 🔴 [重要] システムメンテナンス  │ ← 未読・優先度アイコン
│   2026/07/16  📎 添付あり      │
├────────────────────────────────┤
│ ✅ [通常] 戦闘レポート #1234   │ ← 既読
│   2026/07/15  受取済み         │
├────────────────────────────────┤
│ 🔒 [保護] ランキング報酬       │ ← 保護済み
│   2026/07/14  📎 未受取        │
└────────────────────────────────┘
```

### 詳細ビュー
```
┌────────────────────────────────┐
│ ← システムメンテナンスのお知らせ │
├────────────────────────────────┤
│ 送信者: システム運営            │
│ 日時: 2026/07/16 10:00        │
│ 期限: 2026/08/16まで          │
├────────────────────────────────┤
│ いつもご利用ありがとうござい... │
│ （本文）                       │
├────────────────────────────────┤
│ 添付アイテム:                  │
│  💎 ダイヤ x 100               │
│  🏅 アイテム x 5               │
│                                │
│     [すべて受け取る]           │
└────────────────────────────────┘
```

---

## 🚀 実装の優先順位

### Phase 1: 基盤強化（1週間）
1. ✅ DB拡張（カテゴリ、優先度、有効期限）
2. ✅ マイグレーション作成
3. ✅ モデル更新

### Phase 2: コア機能（1週間）
4. ✅ カテゴリフィルタリング
5. ✅ 一括受取API
6. ✅ 保護機能API
7. ✅ 有効期限管理

### Phase 3: 高度な機能（1週間）
8. ✅ テンプレートエンジン
9. ✅ 自動削除バッチ
10. ✅ 統計・分析機能

### Phase 4: UX改善（1週間）
11. ✅ プッシュ通知連携
12. ✅ パフォーマンス最適化
13. ✅ ドキュメント整備

---

## 📚 参考: 一般的なメールシステムの特徴

1. **カテゴリごとのバッジ表示**: 未読数を視覚的に表示
2. **スワイプ操作**: スワイプで削除・保護
3. **クイックアクション**: 長押しでコンテキストメニュー
4. **通知設定**: カテゴリごとに通知ON/OFF
5. **既読管理**: 自動的に既読にする設定
6. **ストレージ管理**: メール保存上限数の設定

---

次のステップ: どの機能から実装しますか？
