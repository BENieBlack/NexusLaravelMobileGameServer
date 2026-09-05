<?php

namespace Tests\Feature\Models\Sys;

use App\Models\Sys\SysPlayer;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * SysPlayer のテスト
 *
 * アカウントの本体。レベル・経験値のほか、VIPポイントと
 * 累積課金額を持つ。加算メソッドが上書きになっていると
 * 課金の積み上がりが消える。
 *
 * 次のレベルまでの残り経験値はマスターを見て計算する。
 */
class SysPlayerTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUpMaster();
        $this->makeLevels();
    }

    protected function tearDown(): void
    {
        $this->cleanUpMaster();

        parent::tearDown();
    }

    // ========================================
    // 値の出し入れ
    // ========================================

    #[Test]
    public function 値を設定して読み出せる(): void
    {
        $player = new SysPlayer;
        $player->setUuid('uuid-001');
        $player->setMyId('MYID0001');
        $player->setName('テストプレイヤー');
        $player->setLevel(3);
        $player->setLevelExp(300);
        $player->setLastLoginAt('2026-03-15 12:00:00');

        $this->assertSame('uuid-001', $player->getUuid());
        $this->assertSame('MYID0001', $player->getMyId());
        $this->assertSame('テストプレイヤー', $player->getName());
        $this->assertSame(3, $player->getLevel());
        $this->assertSame(300, $player->getLevelExp());
        $this->assertSame('2026-03-15 12:00:00', $player->getLastLoginAt());
    }

    #[Test]
    public function 名前と最終ログインは未設定でもよい(): void
    {
        $player = new SysPlayer;

        $this->assertNull($player->getName());
        $this->assertNull($player->getLastLoginAt());
    }

    // ========================================
    // VIPと課金額の積み上がり
    // ========================================

    #[Test]
    public function vipポイントは加算される(): void
    {
        // 上書きになっていると購入のたびにリセットされる
        $player = $this->makePlayer();

        $player->addVipPoint(98);
        $player->addVipPoint(50);

        $this->assertSame(148, $player->getVipPoint());
    }

    #[Test]
    public function 累積課金額は加算される(): void
    {
        $player = $this->makePlayer();

        $player->addTotalPaidAmount(980.0);
        $player->addTotalPaidAmount(120.5);

        $this->assertSame(1100.5, $player->getTotalPaidAmount());
    }

    #[Test]
    public function vipポイントと課金額は直接も設定できる(): void
    {
        // 運営による補正で入れ直す経路
        $player = $this->makePlayer();

        $player->setVipPoint(500);
        $player->setTotalPaidAmount(5000.0);

        $this->assertSame(500, $player->getVipPoint());
        $this->assertSame(5000.0, $player->getTotalPaidAmount());
    }

    #[Test]
    public function 採番前でもテーブル定義の既定値を返す(): void
    {
        // 属性が入っていないインスタンスへ加算しても落ちない
        $player = new SysPlayer;

        $this->assertSame(1, $player->getLevel());
        $this->assertSame(0, $player->getLevelExp());
        $this->assertSame(0, $player->getVipPoint());
        $this->assertSame(0.0, $player->getTotalPaidAmount());

        $player->addVipPoint(98);
        $this->assertSame(98, $player->getVipPoint());
    }

    // ========================================
    // レベルとマスターの突き合わせ
    // ========================================

    #[Test]
    public function 次のレベルまでの残り経験値を出せる(): void
    {
        // level 2（累積100）から level 3（250）まで残り150
        $player = $this->makePlayer(level: 2, levelExp: 100);

        $this->assertSame(150, $player->calcExpToNextLevel());
    }

    #[Test]
    public function 必要分を超えて持っていれば残りは0(): void
    {
        $player = $this->makePlayer(level: 2, levelExp: 300);

        $this->assertSame(0, $player->calcExpToNextLevel());
    }

    #[Test]
    public function 最大レベルなら残りは0(): void
    {
        // 次のレベルが存在しない
        $player = $this->makePlayer(level: 5, levelExp: 600);

        $this->assertSame(0, $player->calcExpToNextLevel());
    }

    #[Test]
    public function 現在のレベルのマスターを引ける(): void
    {
        $player = $this->makePlayer(level: 3);

        $this->assertSame(3, $player->getCurrentLevelData()?->getLevel());
        $this->assertSame(70, $player->getMaxStamina());
    }

    #[Test]
    public function 定義の無いレベルならマスターは引けない(): void
    {
        // マスターの配信漏れでレベル定義が欠けても落とさない
        $player = $this->makePlayer(level: 99);

        $this->assertNull($player->getCurrentLevelData());
        $this->assertNull($player->getMaxStamina());
    }

    private function makePlayer(int $level = 1, int $levelExp = 0): SysPlayer
    {
        $player = new SysPlayer;
        $player->setUuid('uuid-001');
        $player->setMyId('MYID0001');
        $player->setLevel($level);
        $player->setLevelExp($levelExp);

        return $player;
    }

    private function makeLevels(): void
    {
        $levels = [1 => [0, 50], 2 => [100, 60], 3 => [250, 70], 4 => [400, 80], 5 => [600, 90]];

        DB::connection('mst')->table('mst_player_level')->insert(
            array_map(
                fn (int $level, array $spec) => [
                    'level' => $level,
                    'required_exp' => $spec[0],
                    'max_stamina' => $spec[1],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                array_keys($levels),
                array_values($levels)
            )
        );

        $this->refreshMstCache();
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_player_level')->delete();
        $this->refreshMstCache();
    }
}
