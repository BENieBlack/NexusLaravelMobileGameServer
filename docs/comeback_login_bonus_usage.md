# カムバックログインボーナス機能 - 使用ガイド

## 概要

カムバックログインボーナスは、一定期間ログインしていなかったプレイヤーが復帰した際に特別な報酬を付与する機能です。

### アーキテクチャ

**Package層（nexus-login）**
- `_BaseLoginBonusService` - ログインボーナス全般の基本機能を提供
- `LoginBonusOrchestrator` - 複数の戦略を統合管理
- `LoginBonusStrategyInterface` - 戦略の共通インターフェース

**Domain層（app/Domain/Login/Services）**
- `LoginBonusService` - 通常ログインボーナス（_BaseLoginBonusServiceを継承）
- `ComeBackLoginBonusService` - カムバックボーナス（_BaseLoginBonusServiceを継承）

この設計により：
- **Package側**: 汎用的なフレームワークを提供（再利用可能）
- **Domain側**: アプリケーション固有のロジックを実装（拡張・カスタマイズ）

## マイグレーション実行

```bash
php artisan migrate
```

以下のマイグレーションが実行されます：
- `2026_08_04_000003_add_comeback_columns_to_login_bonus.php` - mst_login_bonusテーブル拡張
- `2026_08_04_000003_add_absent_days_to_login_bonus_history.php` - trx_login_bonus_historyテーブル拡張

## マスターデータ設定例

### 通常ログインボーナス（従来通り）

```php
// database/seeders/LoginBonusSeeder.php

use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;

// 7日間ループの通常ログインボーナス（1日目）
MstLoginBonus::create([
    'id' => 'daily_login_day1',
    'type' => 'daily',
    'day' => 1,
    'loop_days' => 7,
    'required_absent_days' => null,
    'valid_days' => null,
    'priority' => 0,
    'is_active' => true,
]);

// 報酬内容
MstLoginBonusContent::create([
    'mst_login_bonus_id' => 'daily_login_day1',
    'content_type' => 'diamond',
    'content_mst_id' => 'free',
    'content_quantity' => 100,
    'amount' => 1,
    'is_paid' => false,
    'sort_order' => 1,
]);
```

### カムバックログインボーナス

```php
// 7日間休眠プレイヤー向けカムバックボーナス
MstLoginBonus::create([
    'id' => 'comeback_7days',
    'type' => 'comeback',
    'day' => 0, // 未使用
    'loop_days' => 0, // 未使用
    'required_absent_days' => 7, // 7日間ログインなし
    'valid_days' => 7, // 7日間有効
    'priority' => 1,
    'is_active' => true,
]);

// 豪華な報酬
MstLoginBonusContent::create([
    'mst_login_bonus_id' => 'comeback_7days',
    'content_type' => 'diamond',
    'content_mst_id' => 'free',
    'content_quantity' => 500,
    'amount' => 1,
    'is_paid' => false,
    'sort_order' => 1,
]);

// 30日間休眠プレイヤー向け（さらに豪華）
MstLoginBonus::create([
    'id' => 'comeback_30days',
    'type' => 'comeback',
    'day' => 0,
    'loop_days' => 0,
    'required_absent_days' => 30, // 30日間ログインなし
    'valid_days' => 14, // 14日間有効
    'priority' => 2, // 優先度高（7日より豪華）
    'is_active' => true,
]);

MstLoginBonusContent::create([
    'mst_login_bonus_id' => 'comeback_30days',
    'content_type' => 'diamond',
    'content_mst_id' => 'free',
    'content_quantity' => 2000,
    'amount' => 1,
    'is_paid' => false,
    'sort_order' => 1,
]);
```

### 期間限定カムバックボーナス

```php
// 年末年始限定カムバックボーナス
MstLoginBonus::create([
    'id' => 'comeback_newyear_2027',
    'type' => 'comeback',
    'day' => 0,
    'loop_days' => 0,
    'required_absent_days' => 14,
    'valid_days' => 7,
    'priority' => 3, // 最優先
    'is_active' => true,
    'start_at' => '2026-12-28 00:00:00',
    'end_at' => '2027-01-10 23:59:59',
]);
```

## コントローラーでの使用

### 方法1: Orchestratorを使用（推奨）

通常ログインボーナスとカムバックボーナスを一括処理

```php
namespace App\Http\Controllers\Api;

use NexusLogin\Services\LoginBonusOrchestrator;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginBonusOrchestrator $loginBonusOrchestrator,
    ) {}

    public function login(Request $request)
    {
        // ... 認証処理 ...

        // 全てのログインボーナス戦略を実行
        $loginBonuses = $this->loginBonusOrchestrator->executeAll(
            sysPlayerId: $player->getId(),
            lastLoginAt: $player->getLastLoginAt(),
            connectionName: 'trx1' // シャード名
        );

        // 結果は以下の形式で返る:
        // [
        //     'daily' => [Resource, ...],    // 通常ログインボーナス
        //     'comeback' => [Resource, ...], // カムバックボーナス
        // ]

        return response()->json([
            'player' => $player,
            'login_bonuses' => [
                'daily' => $loginBonuses['daily'],
                'comeback' => $loginBonuses['comeback'],
            ],
        ]);
    }
}
```

### 方法2: 統合された報酬を取得

```php
// 全報酬を1つの配列で取得
$allRewards = $this->loginBonusOrchestrator->executeAllMerged(
    sysPlayerId: $player->getId(),
    lastLoginAt: $player->getLastLoginAt(),
    connectionName: 'trx1'
);

// [Resource, Resource, ...] 形式で返る
```

### 方法3: 個別サービスを使用

```php
use NexusLogin\Services\LoginBonusService;
use NexusLogin\Services\ComeBackLoginBonusService;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginBonusService $loginBonusService,
        private readonly ComeBackLoginBonusService $comeBackBonusService,
    ) {}

    public function login(Request $request)
    {
        // 通常ログインボーナス
        if ($this->loginBonusService->isEligible($playerId, $lastLoginAt)) {
            $dailyRewards = $this->loginBonusService->process($playerId, $lastLoginAt, 'trx1');
        }

        // カムバックボーナス
        if ($this->comeBackBonusService->isEligible($playerId, $lastLoginAt)) {
            $comebackRewards = $this->comeBackBonusService->process($playerId, $lastLoginAt, 'trx1');
        }
    }
}
```

## 判定ロジック

### カムバック対象判定

1. 最終ログイン日時から現在までの日数を計算
2. `required_absent_days`以上の休眠日数があるカムバックボーナスを検索
3. 優先度（`priority`）が高い順、必要休眠日数が長い順にソート
4. 期間限定の場合は`start_at`〜`end_at`の範囲内かチェック
5. 有効期間内に既に受け取っていないかチェック

### 例

| 最終ログイン | 休眠日数 | 該当するカムバックボーナス |
|------------|---------|------------------------|
| 5日前      | 5日     | なし |
| 10日前     | 10日    | comeback_7days (7日以上) |
| 35日前     | 35日    | comeback_30days (30日以上、優先度高) |

## 履歴データの確認

```php
use Illuminate\Support\Facades\DB;

// プレイヤーの全ログインボーナス履歴
$history = DB::connection('trx1')
    ->table('trx_login_bonus_history')
    ->where('sys_player_id', $playerId)
    ->orderBy('received_date', 'desc')
    ->get();

// カムバックボーナスのみ
$comebackHistory = DB::connection('trx1')
    ->table('trx_login_bonus_history')
    ->where('sys_player_id', $playerId)
    ->whereNotNull('absent_days')
    ->orderBy('received_date', 'desc')
    ->get();

// absent_daysカラムで判別可能:
// - null: 通常ログインボーナス
// - 数値: カムバックログインボーナス（休眠日数）
```

## テスト例

```php
use Tests\TestCase;
use NexusLogin\Services\ComeBackLoginBonusService;

class ComeBackLoginBonusTest extends TestCase
{
    public function test_comeback_bonus_7days()
    {
        // 7日前にログインしたプレイヤーを作成
        $player = $this->createPlayer();
        $lastLoginAt = now()->subDays(7)->toDateTimeString();

        $service = app(ComeBackLoginBonusService::class);

        // カムバック対象かチェック
        $this->assertTrue($service->isEligible($player->id, $lastLoginAt));

        // カムバックボーナスを配布
        $rewards = $service->process($player->id, $lastLoginAt, 'trx1');

        $this->assertNotEmpty($rewards);
        $this->assertInstanceOf(Resource::class, $rewards[0]);
    }

    public function test_comeback_bonus_not_eligible_if_less_than_7days()
    {
        // 5日前にログインしたプレイヤー
        $player = $this->createPlayer();
        $lastLoginAt = now()->subDays(5)->toDateTimeString();

        $service = app(ComeBackLoginBonusService::class);

        // カムバック対象外
        $this->assertFalse($service->isEligible($player->id, $lastLoginAt));
    }
}
```

## 拡張性

### 新しいタイプのログインボーナスを追加

```php
// 例: VIP専用ログインボーナス
class VipLoginBonusService implements LoginBonusStrategyInterface
{
    public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool
    {
        // VIPレベルをチェック
        $player = $this->playerRepository->findById($sysPlayerId);
        return $player && $player->getVipLevel() >= 5;
    }

    public function process(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): array
    {
        // VIP専用報酬を配布
    }
}

// AppServiceProvider.phpで登録
$orchestrator->registerStrategy(
    $app->make(VipLoginBonusService::class),
    150 // 優先度
);
```

## トラブルシューティング

### カムバックボーナスが付与されない

1. `mst_login_bonus`テーブルの`type`が`'comeback'`になっているか確認
2. `required_absent_days`が休眠日数以下になっているか確認
3. `is_active`が`true`になっているか確認
4. 有効期間内に既に受け取っていないか確認（`trx_login_bonus_history`）
5. 期間限定の場合、`start_at`〜`end_at`の範囲内か確認

### 両方のボーナスが付与される

これは正常な動作です。通常ログインボーナスとカムバックボーナスは両立可能です。
片方のみに制限したい場合は、コントローラー側で制御してください。

## まとめ

- **通常ログインボーナス**: `type='daily'`、毎日のログイン継続を促進
- **カムバックボーナス**: `type='comeback'`、休眠プレイヤーの復帰を促進
- **Orchestrator**: 複数の戦略を統合管理、拡張性が高い
- **履歴管理**: `absent_days`カラムで通常とカムバックを区別

詳細な設計については `/docs/comeback_login_bonus_design.md` を参照してください。
