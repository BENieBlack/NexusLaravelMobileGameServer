# Nexus VIP System

課金額に応じてVIPポイントを管理し、VIPレベルに応じた特典を提供するパッケージ。

## 特徴

- **sys_player統合**: VIPポイントは`sys_player.vip_point`で管理（全シャード共通）
- **動的VIPレベル判定**: VIPレベルはカラムとして保存せず、`vip_point`と`mst_vip_level`から動的に計算
- **クライアント側判定可能**: クライアントもマスターデータを参照してVIPレベルを判定可能
- **商品マスター定義**: 課金商品ごとにVIPポイントを定義（mst_in_app_purchase.vip_point）
- **特典システム**: スタミナボーナス、ショップ割引、ガチャ割引、デイリーダイヤモンド
- **監査ログ**: 全VIPポイント変動を記録
- **イベント駆動**: VIPレベルアップ時にイベント発火

## データベース設計

### sys_player（既存テーブルに追加）

```sql
ALTER TABLE sys_player ADD COLUMN (
    vip_point INT UNSIGNED DEFAULT 0 COMMENT '累積VIPポイント',
    total_paid_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT '累積課金額（日本円換算）',
    
    INDEX idx_vip_point (vip_point)
);
```

**VIPレベル判定方法:**

サーバー側:
```php
$vipLevel = $vipLevelService->calculateLevel($player->getVipPoint());
```

クライアント側:
```javascript
function calculateVipLevel(vipPoint, vipLevelMaster) {
    for (let i = vipLevelMaster.length - 1; i >= 0; i--) {
        if (vipPoint >= vipLevelMaster[i].required_point) {
            return vipLevelMaster[i].level;
        }
    }
    return 0;
}
```

## インストール

### 1. パッケージ登録

`api/composer.json` に追加:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/nexus-vip"
        }
    ],
    "require": {
        "nexus/vip": "*"
    }
}
```

### 2. Composer更新

```bash
cd api
composer update nexus/vip
```

### 3. マイグレーション実行

```bash
php artisan migrate --database=sys   # sys_player にVIPカラム追加
php artisan migrate --database=mst   # mst_vip_level, mst_in_app_purchase.vip_point 追加
php artisan migrate --database=log   # log_vip_point 作成
```

### 4. 設定ファイル公開（オプション）

```bash
php artisan vendor:publish --tag=vip-config
```

## 使用方法

### VIPポイント付与（課金時）

```php
use NexusVip\Services\VipPointService;
use App\Models\Mst\MstInAppPurchase;

class DiamondService
{
    public function __construct(
        protected VipPointService $vipPointService
    ) {}

    public function purchaseDiamond(MstInAppPurchase $product, float $unitPriceJpy, ...): void
    {
        // VIPポイント付与（商品マスターのvip_pointを使用）
        $this->vipPointService->addPoints(
            sysPlayerId: $sysPlayerId,
            points: $product->getVipPoint(),
            reason: 'purchase',
            metadata: [
                'unique_request_id' => $uniqueRequestId,
                'purchase_amount_jpy' => $unitPriceJpy,
                'mst_in_app_purchase_id' => $product->getId(),
            ]
        );
    }
}
```

### VIP特典適用

```php
use App\Models\Sys\SysPlayer;
use NexusVip\Services\VipBenefitService;
use NexusVip\Services\VipLevelService;

class StaminaService
{
    public function __construct(
        protected VipBenefitService $vipBenefitService,
        protected VipLevelService $vipLevelService
    ) {}

    public function getMaxStamina(SysPlayer $player, int $baseMaxStamina): int
    {
        // vip_point から VIPレベルを動的に計算
        $vipLevel = $this->vipLevelService->calculateLevel($player->getVipPoint());
        
        // VIPボーナス適用
        return $this->vipBenefitService->applyStaminaBonus($baseMaxStamina, $vipLevel);
    }
}
```

### VIPレベル一覧取得

```php
use NexusVip\Services\VipLevelService;

class VipController
{
    public function __construct(
        protected VipLevelService $vipLevelService
    ) {}

    public function getLevels(): array
    {
        return [
            'levels' => $this->vipLevelService->getAllLevels()
        ];
    }
}
```

## 設定

`config/vip.php`:

```php
return [
    // 特典有効化フラグ
    'benefits_enabled' => [
        'stamina_bonus' => true,
        'daily_diamond' => true,
        'shop_discount' => true,
        'gacha_discount' => true,
    ],
    
    // ログ・イベント設定
    'enable_point_log' => true,
    'enable_level_up_event' => true,
];
```

## VIPポイント付与方法

商品マスター（`mst_in_app_purchase.vip_point`）で定義します。

### 設定例

```sql
-- 100円パック → 100pt
INSERT INTO mst_in_app_purchase (..., vip_point) VALUES (..., 100);

-- 500円パック → 500pt
INSERT INTO mst_in_app_purchase (..., vip_point) VALUES (..., 500);

-- 特別パック: 500円 → 1000pt（2倍ポイント）
INSERT INTO mst_in_app_purchase (..., vip_point) VALUES (..., 1000);

-- 初回限定: 500円 → 1500pt（3倍ポイント）
INSERT INTO mst_in_app_purchase (..., vip_point) VALUES (..., 1500);
```

## VIPレベルマスターデータ

### VIPレベルと効果

| Level | 必要ポイント | スタミナボーナス | デイリーダイヤ | ショップ割引 | ガチャ割引 |
|-------|------------|----------------|--------------|------------|----------|
| 0     | 0          | 0              | 0            | 0%         | 0%       |
| 1     | 100        | 10             | 10           | 2%         | 0%       |
| 2     | 500        | 20             | 20           | 3%         | 2%       |
| 3     | 1,000      | 30             | 30           | 5%         | 3%       |
| 5     | 5,000      | 70             | 70           | 10%        | 7%       |
| 10    | 100,000    | 200            | 200          | 25%        | 20%      |

### VIPレベルアップ報酬

報酬は`content_type`, `content_id`, `content_option`, `content_quantity`, `amount`で定義されます。

**コンテンツ構造:**
- `content_quantity`: 1配布あたりの数量
- `amount`: 配布回数
- **実際の配布量 = `content_quantity` × `amount`**

| Level | 報酬 | 設定例 |
|-------|------|--------|
| 1     | ダイヤ100個 | content_type='diamond', content_quantity=100, amount=1 |
| 2     | ダイヤ200個 + スタミナ50 | 2つのレコード |
| 3     | ダイヤ300個 | content_type='diamond', content_quantity=300, amount=1 |
| 4     | ダイヤ500個 + スタミナ100 | 2つのレコード |
| 5     | ダイヤ1000個 | content_type='diamond', content_quantity=1000, amount=1 |
| 6     | ダイヤ1500個 + スタミナ150 | 2つのレコード |
| 7     | ダイヤ2000個 | content_type='diamond', content_quantity=2000, amount=1 |
| 8     | ダイヤ3000個 + スタミナ200 | 2つのレコード |
| 9     | ダイヤ5000個 | content_type='diamond', content_quantity=5000, amount=1 |
| 10    | ダイヤ10000個 + スタミナ300 | 2つのレコード |

**応用例:**
```sql
-- グレード1の装備を5個配布
INSERT INTO mst_vip_level_reward 
(vip_level, content_type, content_id, content_option, content_quantity, amount)
VALUES (3, 'equipment', 'equipment01', '{"grade":1}', 1, 5);

-- レベル5のユニットを3体配布
INSERT INTO mst_vip_level_reward 
(vip_level, content_type, content_id, content_option, content_quantity, amount)
VALUES (5, 'unit', 'unit001', '{"level":5}', 1, 3);
```

## イベント

### VipLevelUpEvent

VIPレベルアップ時に発火されます。報酬情報も含まれます。

```php
use NexusVip\Events\VipLevelUpEvent;

// イベントリスナー登録（EventServiceProvider）
protected $listen = [
    VipLevelUpEvent::class => [
        VipLevelUpRewardListener::class,  // 報酬付与
        VipLevelUpNotificationListener::class,  // 通知送信
    ],
];

// リスナー実装例
class VipLevelUpRewardListener
{
    public function handle(VipLevelUpEvent $event): void
    {
        // $event->sysPlayerId
        // $event->beforeLevel
        // $event->afterLevel
        // $event->rewards - レベルアップ報酬リスト
        
        // 報酬を付与
        foreach ($event->rewards as $reward) {
            $contentType = $reward['content_type'];      // item, unit, equipment, diamond, wallet, stamina
            $contentId = $reward['content_id'];          // コンテンツID
            $contentOption = $reward['content_option'];  // {"grade":1, "level":5} 等
            $contentQuantity = $reward['content_quantity']; // 1配布あたりの数量
            $amount = $reward['amount'];                 // 配布回数
            $totalQuantity = $reward['total_quantity'];  // content_quantity × amount
            $isPaid = $reward['is_paid'];                // 有償フラグ
            
            $this->grantReward(
                $event->sysPlayerId,
                $contentType,
                $contentId,
                $contentOption,
                $contentQuantity,
                $amount,
                $isPaid
            );
        }
    }
}
```

**複数レベルアップの場合:**
```php
// VIP1 → VIP3 に一気にレベルアップした場合
// $event->rewards には VIP2とVIP3の報酬が全て含まれる
if ($event->isMultipleLevelUp()) {
    $levelUpCount = $event->getLevelUpCount(); // 2
}
```

## ライセンス

MIT
