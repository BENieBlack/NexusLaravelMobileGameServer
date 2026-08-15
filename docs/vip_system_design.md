# VIPポイント管理システム設計書

作成日: 2026-08-04  
バージョン: 3.0（vip_level動的計算版）

## 概要

課金額に応じてVIPポイントを付与し、VIPレベルに応じた特典を提供するシステム。  
VIPレベルに応じた特典（スタミナ上限増加、ショップ割引、デイリー報酬増加など）を提供。

**設計方針:**
- VIPポイントは `sys_player.vip_point` で管理（全シャード共通）
- 課金時に `sys_player.vip_point` をインクリメント
- **VIPレベルはカラムとして保存せず、`vip_point`と`mst_vip_level`から動的に計算**
- クライアント側でもマスターデータを参照してVIPレベルを判定可能

---

## 1. データベース設計

### 1.1 システムデータ（sys）

#### sys_player（既存テーブルに追加）

プレイヤー基本情報にVIPポイント関連カラムを追加。

**追加カラム:**
```sql
ALTER TABLE sys_player ADD COLUMN (
    vip_point INT UNSIGNED DEFAULT 0 COMMENT '累積VIPポイント',
    total_paid_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT '累積課金額（日本円換算）',
    
    INDEX idx_vip_point (vip_point)
);
```

**特徴:**
- `vip_point`: 累積VIPポイント（減少しない、課金時にインクリメント）
- `total_paid_amount`: 累積課金額（分析用、日本円換算）
- **`vip_level`カラムは不要**（動的計算のため）
- 全シャードで共有される（sysデータベースに保存）

**VIPレベル判定方法:**
```sql
-- サーバー側での判定例
SELECT 
    p.id,
    p.vip_point,
    (SELECT MAX(level) FROM mst_vip_level WHERE required_point <= p.vip_point) as vip_level
FROM sys_player p;
```

**クライアント側での判定:**
```javascript
// クライアントは mst_vip_level を持っている前提
function calculateVipLevel(vipPoint, vipLevelMaster) {
    for (let i = vipLevelMaster.length - 1; i >= 0; i--) {
        if (vipPoint >= vipLevelMaster[i].required_point) {
            return vipLevelMaster[i].level;
        }
    }
    return 0;
}
```

---

### 1.2 マスターデータ（mst）

#### mst_vip_level

VIPレベル定義とレベルアップに必要なポイント。

```sql
CREATE TABLE mst_vip_level (
    deploy_key INT DEFAULT 202601010 COMMENT 'デプロイキー',
    id VARCHAR(50) PRIMARY KEY COMMENT 'VIPレベルID (vip_0, vip_1, ...)',
    level SMALLINT NOT NULL UNIQUE COMMENT 'VIPレベル (0-15)',
    required_point INT UNSIGNED NOT NULL COMMENT 'このレベルに到達するために必要な累積VIPポイント',
    max_stamina_bonus SMALLINT UNSIGNED DEFAULT 0 COMMENT 'スタミナ上限ボーナス',
    daily_diamond_bonus SMALLINT UNSIGNED DEFAULT 0 COMMENT 'デイリーダイヤモンドボーナス',
    shop_discount_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'ショップ割引率 (0.00-1.00)',
    gacha_discount_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'ガチャ割引率 (0.00-1.00)',
    display_badge_url VARCHAR(255) NULLABLE COMMENT 'VIPバッジ画像URL',
    sort_desc INT UNSIGNED DEFAULT 0 COMMENT '表示順序（降順）',
    is_active BOOLEAN DEFAULT TRUE COMMENT '有効フラグ',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_deploy_key (deploy_key),
    INDEX idx_level (level),
    INDEX idx_required_point (required_point)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VIPレベルマスター';
```

**マスターデータ例:**

| id    | level | required_point | max_stamina_bonus | daily_diamond_bonus | shop_discount_rate | gacha_discount_rate |
|-------|-------|---------------|-------------------|---------------------|-------------------|---------------------|
| vip_0 | 0     | 0             | 0                 | 0                   | 0.00              | 0.00                |
| vip_1 | 1     | 100           | 10                | 10                  | 0.02              | 0.00                |
| vip_2 | 2     | 500           | 20                | 20                  | 0.03              | 0.02                |
| vip_3 | 3     | 1000          | 30                | 30                  | 0.05              | 0.03                |
| vip_4 | 4     | 3000          | 50                | 50                  | 0.07              | 0.05                |
| vip_5 | 5     | 5000          | 70                | 70                  | 0.10              | 0.07                |
| vip_6 | 6     | 10000         | 100               | 100                 | 0.12              | 0.10                |
| vip_7 | 7     | 20000         | 120               | 120                 | 0.15              | 0.12                |
| vip_8 | 8     | 30000         | 150               | 150                 | 0.17              | 0.15                |
| vip_9 | 9     | 50000         | 180               | 180                 | 0.20              | 0.17                |
| vip_10| 10    | 100000        | 200               | 200                 | 0.25              | 0.20                |

> **VIPポイント付与方法**:
> - 商品マスター（mst_in_app_purchase.vip_point）で定義（必須）
> - 課金額からの自動計算は行わない

> **VIPレベルアップ報酬**:
> - VIPレベルアップ時に報酬を自動付与
> - 報酬は mst_vip_level_reward で定義
> - 複数レベルアップした場合は全レベルの報酬を付与

---

### 1.3 課金商品マスター（VIPポイント定義）

#### mst_in_app_purchase（既存テーブルに追加）

課金商品ごとにVIPポイントを定義（必須）。

**追加カラム:**
```sql
ALTER TABLE mst_in_app_purchase ADD COLUMN (
    vip_point INT UNSIGNED DEFAULT 0 COMMENT '付与VIPポイント',
    
    INDEX idx_vip_point (vip_point)
);
```

**使用例:**
```sql
-- 100円パック → 100pt
INSERT INTO mst_in_app_purchase (id, type, paid_diamond_amount, vip_point, ...)
VALUES (1, 'Diamond', 50, 100, ...);

-- 500円パック → 500pt
INSERT INTO mst_in_app_purchase (id, type, paid_diamond_amount, vip_point, ...)
VALUES (2, 'Diamond', 250, 500, ...);

-- 特別パック（2倍ポイント） → 1000pt
INSERT INTO mst_in_app_purchase (id, type, paid_diamond_amount, vip_point, ...)
VALUES (3, 'Pack', 500, 1000, ...);
```

---

### 1.4 VIPレベルアップ報酬マスター

#### mst_vip_level_reward

VIPレベルアップ時に付与される報酬を定義。既存のコンテンツ構造（content_type/content_id）に準拠し、`content_option`, `content_quantity`, `amount`による柔軟な報酬設定をサポート。

```sql
CREATE TABLE mst_vip_level_reward (
    deploy_key INT DEFAULT 202601010 COMMENT 'デプロイキー',
    vip_level SMALLINT NOT NULL COMMENT 'VIPレベル',
    content_type ENUM('item', 'unit', 'equipment', 'diamond', 'wallet', 'stamina') NOT NULL COMMENT 'コンテンツタイプ',
    content_id VARCHAR(255) NOT NULL COMMENT 'コンテンツID (mst_item_id等、diamond/stamina/walletはダミー値)',
    content_option JSON NULLABLE COMMENT 'コンテンツオプション (例: {"grade":1, "level":5})',
    content_quantity INT UNSIGNED DEFAULT 1 COMMENT '1配布あたりのコンテンツ数量',
    amount INT UNSIGNED DEFAULT 1 COMMENT '配布回数（content_quantity × amount = 実際の配布量）',
    is_paid BOOLEAN DEFAULT FALSE COMMENT '有償フラグ（wallet/diamondの場合）',
    sort_order INT UNSIGNED DEFAULT 0 COMMENT '表示順序',
    is_active BOOLEAN DEFAULT TRUE COMMENT '有効フラグ',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (vip_level, content_type, content_id),
    INDEX idx_deploy_key (deploy_key),
    INDEX idx_vip_level_sort (vip_level, sort_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VIPレベルアップ報酬マスター';
```

**コンテンツ構造:**
- `content_type`: コンテンツの種類
- `content_id`: コンテンツを識別するID
- `content_option`: コンテンツの付加情報（JSON形式）
- `content_quantity`: 1配布あたりの数量
- `amount`: 配布回数
- **実際の配布量 = `content_quantity` × `amount`**

**コンテンツタイプ:**
- `diamond`: ダイヤモンド（content_idは'free'または'paid'）
- `wallet`: ゲーム内通貨（content_idに通貨種類を指定）
- `stamina`: スタミナ（content_idは'stamina'）
- `item`: アイテム（content_idにmst_item.idを指定）
- `unit`: ユニット（content_idにmst_unit.idを指定）
- `equipment`: 装備（content_idにmst_equipment.idを指定）

**マスターデータ例:**

| vip_level | content_type | content_id | content_option | content_quantity | amount | 実際の配布量 | 説明 |
|-----------|--------------|------------|----------------|-----------------|--------|------------|------|
| 1         | diamond      | free       | null           | 100             | 1      | 100        | VIP1達成で無償100ダイヤ |
| 2         | diamond      | free       | null           | 200             | 1      | 200        | VIP2達成で無償200ダイヤ |
| 2         | stamina      | stamina    | null           | 50              | 1      | 50         | VIP2達成でスタミナ50 |
| 3         | equipment    | eq001      | {"grade":1}    | 1               | 5      | 5          | VIP3達成でグレード1装備5個 |
| 5         | diamond      | free       | null           | 1000            | 1      | 1000       | VIP5達成で無償1000ダイヤ |
| 10        | diamond      | free       | null           | 10000           | 1      | 10000      | VIP10達成で無償10000ダイヤ |
| 10        | stamina      | stamina    | null           | 300             | 1      | 300        | VIP10達成でスタミナ300 |

**使用例:**
```php
// 例1: ダイヤ100個
content_type='diamond', content_id='free', content_option=null, content_quantity=100, amount=1
→ 実際の配布量: 100個

// 例2: グレード1の装備5個
content_type='equipment', content_id='equipment01', content_option={"grade":1}, content_quantity=1, amount=5
→ 実際の配布量: 5個（各1個のグレード1装備を5回配布）

// 例3: レベル5のユニット3体
content_type='unit', content_id='unit001', content_option={"level":5}, content_quantity=1, amount=3
→ 実際の配布量: 3体（各1体のレベル5ユニットを3回配布）
```

---

### 1.5 ログデータ（log）

#### log_vip_point

VIPポイント変動ログ（監査・分析用）。

```sql
CREATE TABLE log_vip_point (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    unique_request_id VARCHAR(255) NOT NULL COMMENT 'リクエスト一意ID',
    sys_player_id BIGINT UNSIGNED NOT NULL COMMENT 'sys_playerテーブルのID',
    before_vip_level SMALLINT UNSIGNED NOT NULL COMMENT '変更前VIPレベル',
    after_vip_level SMALLINT UNSIGNED NOT NULL COMMENT '変更後VIPレベル',
    before_vip_point INT UNSIGNED NOT NULL COMMENT '変更前VIPポイント',
    after_vip_point INT UNSIGNED NOT NULL COMMENT '変更後VIPポイント',
    point_diff INT NOT NULL COMMENT 'ポイント増減量',
    reason VARCHAR(100) NOT NULL COMMENT '変更理由 (purchase, manual_adjustment, campaign)',
    purchase_amount DECIMAL(10,2) NULLABLE COMMENT '課金額（課金起因の場合）',
    currency_code VARCHAR(3) NULLABLE COMMENT '通貨コード (JPY, USD...)',
    mst_in_app_purchase_id VARCHAR(50) NULLABLE COMMENT 'アプリ内課金マスターID',
    system_at DATETIME NOT NULL COMMENT 'APIの日時',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_sys_player_id (sys_player_id),
    INDEX idx_reason (reason),
    INDEX idx_system_at (system_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VIPポイント変動ログ';
```

---

## 2. パッケージ構成

### 2.1 新規パッケージ: nexus-vip

```
packages/nexus-vip/
├── src/
│   ├── Services/
│   │   ├── VipPointService.php          # VIPポイント計算・付与
│   │   ├── VipLevelService.php          # VIPレベル判定・特典計算
│   │   ├── VipBenefitService.php        # VIP特典適用
│   │   └── VipRewardService.php         # VIPレベルアップ報酬取得
│   ├── Repositories/
│   │   ├── VipLevelRepositoryInterface.php
│   │   ├── VipLevelRewardRepositoryInterface.php
│   │   ├── PlayerVipRepositoryInterface.php
│   │   └── VipPointLogRepositoryInterface.php
│   ├── Models/
│   │   ├── MstVipLevel.php              # VIPレベルマスター
│   │   └── MstVipLevelReward.php        # VIPレベルアップ報酬マスター
│   ├── DTOs/
│   │   ├── VipInfo.php               # VIP情報レスポンス
│   │   ├── VipBenefit.php            # VIP特典情報
│   │   └── VipReward.php             # VIPレベルアップ報酬情報
│   ├── Events/
│   │   └── VipLevelUpEvent.php          # VIPレベルアップイベント
│   ├── Exceptions/
│   │   ├── VipLevelNotFoundException.php
│   │   └── InvalidVipPointException.php
│   └── VipServiceProvider.php
├── tests/
│   ├── Unit/
│   │   ├── VipPointServiceTest.php
│   │   └── VipLevelServiceTest.php
│   └── Integration/
│       └── VipPointFlowTest.php
├── config/
│   └── vip.php
├── composer.json
└── README.md
```

---

## 3. ドメインモデル

### 3.1 VipPointService

VIPポイントの計算と付与を担当。

```php
namespace NexusVip\Services;

use App\Models\Sys\SysPlayer;

class VipPointService
{
    /**
     * VIPポイントを付与
     *
     * @param int $sysPlayerId
     * @param int $points
     * @param string $reason
     * @param array $metadata
     * @return SysPlayer
     */
    public function addPoints(
        int $sysPlayerId,
        int $points,
        string $reason,
        array $metadata = []
    ): SysPlayer {
        // sys_player を取得
        $player = $this->playerVipRepository->findById($sysPlayerId);
        
        $beforePoint = $player->getVipPoint();
        
        // 変更前のVIPレベルを計算
        $beforeLevel = $this->vipLevelService->calculateLevel($beforePoint);
        
        // ポイント加算（sys_player.vip_point をインクリメント）
        $player->addVipPoint($points);
        
        // 課金額を累積
        if (isset($metadata['purchase_amount'])) {
            $amountInJpy = $this->currencyConverter->convertToJpy(
                $metadata['purchase_amount'],
                $metadata['currency_code'] ?? 'JPY'
            );
            $player->addTotalPaidAmount($amountInJpy);
        }
        
        // 変更後のVIPレベルを計算
        $afterLevel = $this->vipLevelService->calculateLevel($player->getVipPoint());
        
        // Repository に登録（Unit of Workパターン）
        // Note: vip_level カラムは保存しない（クライアント側で計算）
        $this->playerVipRepository->setModel($player);
        
        // ログ記録
        $this->logVipPointChange(
            $sysPlayerId,
            $beforeLevel,
            $afterLevel,
            $beforePoint,
            $player->getVipPoint(),
            $points,
            $reason,
            $metadata
        );
        
        // レベルアップ時のイベント発火
        if ($afterLevel > $beforeLevel) {
            event(new VipLevelUpEvent($sysPlayerId, $beforeLevel, $afterLevel));
        }
        
        return $player;
    }
}
```

---

### 3.2 VipLevelService

VIPレベルの判定と特典計算。

```php
namespace NexusVip\Services;

class VipLevelService
{
    /**
     * 累積ポイントからVIPレベルを計算
     *
     * @param int $totalPoints
     * @return int VIPレベル
     */
    public function calculateLevel(int $totalPoints): int
    {
        $levels = $this->vipLevelRepository->getAllLevels();
        
        // 降順でチェック（高いレベルから）
        foreach ($levels->sortByDesc('required_point') as $level) {
            if ($totalPoints >= $level->getRequiredPoint()) {
                return $level->getLevel();
            }
        }
        
        return 0; // VIP0（デフォルト）
    }
    
    /**
     * 次のレベルまでの必要ポイントを取得
     *
     * @param int $currentLevel
     * @param int $currentPoint
     * @return int|null 次レベルまでのポイント（最高レベルの場合null）
     */
    public function getPointsToNextLevel(
        int $currentLevel,
        int $currentPoint
    ): ?int {
        $nextLevel = $this->vipLevelRepository->findByLevel($currentLevel + 1);
        
        if ($nextLevel === null) {
            return null; // 最高レベル
        }
        
        $pointsNeeded = $nextLevel->getRequiredPoint() - $currentPoint;
        return max(0, $pointsNeeded);
    }
    
    /**
     * VIPレベルの特典情報を取得
     *
     * @param int $level
     * @return VipBenefit
     */
    public function getBenefits(int $level): VipBenefit
    {
        $vipLevel = $this->vipLevelRepository->findByLevel($level);
        
        if ($vipLevel === null) {
            throw new VipLevelNotFoundException("VIP level {$level} not found");
        }
        
        return new VipBenefit(
            maxStaminaBonus: $vipLevel->getMaxStaminaBonus(),
            dailyDiamondBonus: $vipLevel->getDailyDiamondBonus(),
            shopDiscountRate: $vipLevel->getShopDiscountRate(),
            gachaDiscountRate: $vipLevel->getGachaDiscountRate(),
        );
    }
}
```

---

### 3.3 VipBenefitService

VIP特典の適用。

```php
namespace NexusVip\Services;

class VipBenefitService
{
    /**
     * スタミナ上限にVIPボーナスを適用
     *
     * @param int $baseMaxStamina
     * @param int $vipLevel
     * @return int
     */
    public function applyStaminaBonus(
        int $baseMaxStamina,
        int $vipLevel
    ): int {
        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        return $baseMaxStamina + $benefits->maxStaminaBonus;
    }
    
    /**
     * ショップ価格にVIP割引を適用
     *
     * @param int $basePrice
     * @param int $vipLevel
     * @return int
     */
    public function applyShopDiscount(
        int $basePrice,
        int $vipLevel
    ): int {
        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        $discount = $basePrice * $benefits->shopDiscountRate;
        return max(1, (int) floor($basePrice - $discount));
    }
    
    /**
     * デイリーダイヤモンドボーナスを取得
     *
     * @param int $vipLevel
     * @return int
     */
    public function getDailyDiamondBonus(int $vipLevel): int
    {
        $benefits = $this->vipLevelService->getBenefits($vipLevel);
        return $benefits->dailyDiamondBonus;
    }
}
```

### 3.4 VipRewardService

VIPレベルアップ報酬の取得を担当。

```php
namespace NexusVip\Services;

class VipRewardService
{
    /**
     * VIPレベルに対応する報酬一覧を取得
     *
     * @param int $vipLevel
     * @return array<VipReward>
     */
    public function getRewardsByLevel(int $vipLevel): array
    {
        $rewards = $this->vipLevelRewardRepository->findActiveByVipLevel($vipLevel);

        return $rewards->map(function ($reward) {
            return new VipReward(
                rewardType: $reward->getRewardType(),
                rewardId: $reward->getRewardId(),
                rewardAmount: $reward->getRewardAmount(),
            );
        })->values()->toArray();
    }
    
    /**
     * 報酬があるかチェック
     *
     * @param int $vipLevel
     * @return bool
     */
    public function hasRewards(int $vipLevel): bool
    {
        $rewards = $this->vipLevelRewardRepository->findActiveByVipLevel($vipLevel);
        return $rewards->isNotEmpty();
    }
}
```

---

## 4. 課金システムとの統合

### 4.1 DiamondService への統合

課金完了時にVIPポイントを自動付与。VIPレベルアップ時はイベント発火。

```php
// api/app/Domain/InAppPurchase/Services/DiamondService.php

public function purchaseDiamond(
    int $sysPlayerId,
    MstInAppPurchase $mstInAppPurchase,
    string $platform,
    string $billingPlatform,
    float $unitPrice,
    string $transactionId
): array {
    // ... 既存のダイヤモンド付与処理 ...
    
    // VIPポイント付与（新規追加）
    // 商品マスターのvip_pointを使用
    $this->vipPointService->addPoints(
        sysPlayerId: $sysPlayerId,
        points: $mstInAppPurchase->getVipPoint(),
        reason: 'purchase',
        metadata: [
            'purchase_amount_jpy' => $unitPrice,  // 日本円換算済みの金額
            'mst_in_app_purchase_id' => $mstInAppPurchase->getId(),
            'transaction_id' => $transactionId,
        ]
    );
    
    // ... 既存の返却処理 ...
}
```

### 4.2 VIPレベルアップ報酬付与（イベントリスナー）

VIPレベルアップ時に自動的に報酬を付与する実装例。

```php
// api/app/Listeners/VipLevelUpRewardListener.php

namespace App\Listeners;

use NexusVip\Events\VipLevelUpEvent;
use App\Domain\Item\Services\ItemService;
use App\Domain\Diamond\Services\DiamondService;
use App\Domain\Stamina\Services\StaminaService;

class VipLevelUpRewardListener
{
    public function __construct(
        protected ItemService $itemService,
        protected DiamondService $diamondService,
        protected StaminaService $staminaService,
    ) {}

    public function handle(VipLevelUpEvent $event): void
    {
        // 報酬が空の場合は何もしない
        if (empty($event->rewards)) {
            return;
        }

        // 各報酬を付与
        foreach ($event->rewards as $reward) {
            $this->grantReward(
                $event->sysPlayerId,
                $reward['reward_type'],
                $reward['reward_id'],
                $reward['reward_amount']
            );
        }
    }

    protected function grantReward(
        int $sysPlayerId,
        string $rewardType,
        ?string $rewardId,
        int $rewardAmount
    ): void {
        match ($rewardType) {
            'Diamond' => $this->diamondService->addFreeDiamond(
                $sysPlayerId,
                $rewardAmount,
                'vip_level_up_reward'
            ),
            'Stamina' => $this->staminaService->addStamina(
                $sysPlayerId,
                $rewardAmount,
                'vip_level_up_reward'
            ),
            'Item' => $this->itemService->addItem(
                $sysPlayerId,
                $rewardId,
                $rewardAmount,
                'vip_level_up_reward'
            ),
            default => throw new \InvalidArgumentException("Unknown reward type: {$rewardType}"),
        };
    }
}
```

**EventServiceProvider で登録:**
```php
protected $listen = [
    VipLevelUpEvent::class => [
        VipLevelUpRewardListener::class,
    ],
];
```

---

## 5. API設計

### 5.1 VIP情報取得 API

**エンドポイント:** `GET /api/player/vip`

**レスポンス:**
```json
{
    "vip_level": 5,
    "vip_point": 5240,
    "points_to_next_level": 4760,
    "next_level": 6,
    "benefits": {
        "max_stamina_bonus": 70,
        "daily_diamond_bonus": 70,
        "shop_discount_rate": 0.10,
        "gacha_discount_rate": 0.07
    },
    "total_paid_amount": 5240.00
}
```

### 5.2 VIPレベル一覧取得 API

**エンドポイント:** `GET /api/vip/levels`

**レスポンス:**
```json
{
    "levels": [
        {
            "level": 0,
            "required_point": 0,
            "benefits": {
                "max_stamina_bonus": 0,
                "daily_diamond_bonus": 0,
                "shop_discount_rate": 0.00,
                "gacha_discount_rate": 0.00
            }
        },
        {
            "level": 1,
            "required_point": 100,
            "benefits": {
                "max_stamina_bonus": 10,
                "daily_diamond_bonus": 10,
                "shop_discount_rate": 0.02,
                "gacha_discount_rate": 0.00
            }
        }
        // ... 続く
    ]
}
```

---

## 6. マイグレーション手順

### 6.1 既存プレイヤーのVIPポイント初期化

既存の課金ログから累積課金額を集計し、`sys_player` にVIPポイントを初期付与。

**手順:**

1. `sys_player` テーブルにVIPポイントカラムを追加
2. 既存の課金ログから累積課金額を集計
3. VIPポイントとVIPレベルを計算して更新

```php
// database/migrations/sys/2026_08_04_000002_initialize_vip_points.php

public function up(): void
{
    // 既存プレイヤーの課金履歴から集計してVIPポイント付与
    DB::connection('log')
        ->table('log_in_app_purchase')
        ->select('sys_player_id', DB::raw('SUM(pay_amount) as total_amount'))
        ->where('status', 'Purchased')
        ->groupBy('sys_player_id')
        ->orderBy('sys_player_id')
        ->chunk(1000, function ($purchases) {
            foreach ($purchases as $purchase) {
                // VIPポイント計算（1円 = 1ポイントで仮計算）
                $vipPoint = (int) floor($purchase->total_amount);
                
                // sys_player を更新（vip_levelは不要）
                DB::connection('sys')->table('sys_player')
                    ->where('id', $purchase->sys_player_id)
                    ->update([
                        'vip_point' => $vipPoint,
                        'total_paid_amount' => $purchase->total_amount,
                        'updated_at' => now(),
                    ]);
            }
        });
}
```

---

## 7. データフロー

### 7.1 課金時のVIPポイント付与フロー

```
1. プレイヤーが課金
   ↓
2. DiamondService::purchaseDiamond()
   - レシート検証
   - 価格検証
   - トランザクション開始
   ↓
3. VipPointService::addPoints()
   - sys_player を取得
   - beforeLevel = calculateLevel(vip_point) ← 動的計算
   - vip_point をインクリメント（+=）
   - total_paid_amount を加算
   - afterLevel = calculateLevel(vip_point) ← 動的計算
   ↓
4. sys_player を保存（Unit of Work）
   - UPDATE sys_player SET 
       vip_point = vip_point + {points},
       total_paid_amount = total_paid_amount + {amount}
     WHERE id = ?
   - Note: vip_level カラムは更新しない
   ↓
5. log_vip_point にログ記録（beforeLevel/afterLevelを記録）
   ↓
6. VIPレベルアップ時: VipLevelUpEvent 発火
```

### 7.2 VIP特典適用フロー（サーバー側）

```
1. スタミナ上限取得時
   ↓
2. sys_player.vip_point を取得
   ↓
3. VipLevelService::calculateLevel(vip_point)
   - mst_vip_level を参照して動的にレベル判定
   ↓
4. VipBenefitService::applyStaminaBonus()
   - mst_vip_level から max_stamina_bonus を取得
   - baseMaxStamina + bonus を返却
```

### 7.3 クライアント側のVIPレベル表示フロー

```
1. プレイヤー情報取得
   - sys_player.vip_point を受信
   - mst_vip_level マスターを保持
   ↓
2. クライアント側でVIPレベル判定
   - vip_point と mst_vip_level を比較
   - 該当するVIPレベルを特定
   ↓
3. UI表示
   - VIPレベルバッジ表示
   - 特典情報表示
   - 次レベルまでの進捗表示
```
                
---

## 8. 設定ファイル

### config/vip.php

```php
return [
    // VIPポイント換算率（1円あたりのポイント）
    'point_conversion_rate' => env('VIP_POINT_CONVERSION_RATE', 1.0),
    
    // 通貨換算レート（対日本円）
    'currency_rates' => [
        'JPY' => 1.0,
        'USD' => 140.0,  // 1 USD = 140 JPY
        'EUR' => 150.0,  // 1 EUR = 150 JPY
        'CNY' => 20.0,   // 1 CNY = 20 JPY
    ],
    
    // VIPレベルキャッシュTTL（秒）
    'level_cache_ttl' => 3600,
    
    // VIP特典有効化フラグ
    'benefits_enabled' => [
        'stamina_bonus' => true,
        'daily_diamond' => true,
        'shop_discount' => true,
        'gacha_discount' => true,
    ],
];
```

---

## 8. テスト計画

### 8.1 ユニットテスト

- `VipPointServiceTest`: ポイント計算ロジック
- `VipLevelServiceTest`: レベル判定ロジック
- `VipBenefitServiceTest`: 特典計算ロジック

### 8.2 統合テスト

- `VipPointFlowTest`: 課金→VIPポイント付与→レベルアップの一連の流れ
- `VipBenefitIntegrationTest`: 特典適用の動作確認

### 8.3 Featureテスト

- `VipApiTest`: VIP情報取得APIの動作確認
- `PurchaseWithVipTest`: 課金時のVIPポイント付与確認

---

## 9. 実装優先順位

### Phase 1: 基盤構築（2-3日）
- [ ] マイグレーションファイル作成
- [ ] nexus-vipパッケージの基本構造作成
- [ ] VIPレベルマスターデータ投入

### Phase 2: コアロジック実装（3-4日）
- [ ] VipPointService 実装
- [ ] VipLevelService 実装
- [ ] VipBenefitService 実装
- [ ] Repository実装

### Phase 3: 課金システム統合（2日）
- [ ] DiamondService への統合
- [ ] 既存プレイヤーの初期化マイグレーション
- [ ] VIPポイント付与テスト

### Phase 4: API実装（2日）
- [ ] VIP情報取得API
- [ ] VIPレベル一覧API
- [ ] レスポンスDTO実装

### Phase 5: 特典適用（3-4日）
- [ ] スタミナ上限ボーナス適用
- [ ] デイリーダイヤモンドボーナス配布
- [ ] ショップ割引適用
- [ ] ガチャ割引適用

### Phase 6: テスト・QA（2-3日）
- [ ] ユニットテスト実装
- [ ] 統合テスト実装
- [ ] 手動QA

**合計見積もり:** 14-20日間

---

## 10. 将来の拡張案

### 10.1 VIP期限付きポイント

特定キャンペーンで付与されたVIPポイントに有効期限を設定。

### 10.2 VIPレベル別ミッション

VIPレベルに応じた専用ミッション（高VIPほど報酬が豪華）。

### 10.3 VIP専用コンテンツ

- VIP限定ガチャ
- VIP専用ステージ
- VIP専用アイテム

### 10.4 月間VIPポイント

累積とは別に、月間課金額でランキングやボーナス付与。

---

## まとめ

このVIPポイント管理システムは、課金額に応じて自動的にVIPレベルが上昇し、プレイヤーに明確なメリットを提供します。

**主な特徴:**
- ✅ 累積VIPポイントで永続的な価値を提供
- ✅ 多通貨対応（自動換算）
- ✅ 既存の課金システムとシームレスに統合
- ✅ 監査可能なログ記録
- ✅ 拡張性の高い設計

**ビジネス効果:**
- 課金継続インセンティブの向上
- 高額課金者の満足度向上
- LTV（顧客生涯価値）の向上
