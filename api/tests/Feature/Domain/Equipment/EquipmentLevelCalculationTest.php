<?php

namespace Tests\Feature\Domain\Equipment;

use App\Domain\Equipment\Services\EquipmentLevelService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * EquipmentLevelService のレベル計算テスト
 *
 * 累積経験値からレベルを引き、次のレベルまでの残りを出す。
 * 最大レベル・レベル1・経験値0といった端の扱いを確認する。
 *
 * マスターは required_exp が「そのレベルに到達するのに必要な累積経験値」。
 */
class EquipmentLevelCalculationTest extends TestCase
{
    use RefreshMultipleDatabases;

    /** enumのため既存の値から選ぶ。他テストと衝突しにくいUCを使う */
    private const RARITY = 'UC';

    private EquipmentLevelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUpMaster();
        $this->makeLevelMaster();
        $this->service = app(EquipmentLevelService::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUpMaster();

        parent::tearDown();
    }

    #[Test]
    public function 経験値0はレベル1(): void
    {
        $this->assertSame(1, $this->service->calculateLevelFromExp(self::RARITY, 0));
    }

    #[Test]
    public function 必要経験値に届いていなければレベルは上がらない(): void
    {
        $this->assertSame(1, $this->service->calculateLevelFromExp(self::RARITY, 99));
    }

    #[Test]
    public function 必要経験値ちょうどでレベルが上がる(): void
    {
        $this->assertSame(2, $this->service->calculateLevelFromExp(self::RARITY, 100));
        $this->assertSame(3, $this->service->calculateLevelFromExp(self::RARITY, 300));
    }

    #[Test]
    public function 最大レベルを超える経験値でも最大レベルで止まる(): void
    {
        $this->assertSame(3, $this->service->calculateLevelFromExp(self::RARITY, 999_999));
    }

    #[Test]
    public function 次のレベルまでの残り経験値を出せる(): void
    {
        // レベル1で経験値0 → レベル2まで100
        $this->assertSame(100, $this->service->calcExpToNextLevel(self::RARITY, 1, 0));

        // レベル1で経験値40 → 残り60
        $this->assertSame(60, $this->service->calcExpToNextLevel(self::RARITY, 1, 40));
    }

    #[Test]
    public function 必要分を超えて持っていれば残りは0(): void
    {
        // 次のレベルの必要量を既に超えている場合、マイナスにはしない
        $this->assertSame(0, $this->service->calcExpToNextLevel(self::RARITY, 1, 150));
    }

    #[Test]
    public function 最大レベルなら残りはnull(): void
    {
        $this->assertNull(
            $this->service->calcExpToNextLevel(self::RARITY, 3, 300),
            '次のレベルが定義されていない'
        );
    }

    #[Test]
    public function 定義の無いレアリティは最低レベル扱い(): void
    {
        $this->assertSame(1, $this->service->calculateLevelFromExp('C', 999));
        $this->assertNull($this->service->calcExpToNextLevel('C', 1, 0));
    }

    private function makeLevelMaster(): void
    {
        DB::connection('mst')->table('mst_equipment_level')->insert([
            ['rarity' => self::RARITY, 'level' => 1, 'required_exp' => 0],
            ['rarity' => self::RARITY, 'level' => 2, 'required_exp' => 100],
            ['rarity' => self::RARITY, 'level' => 3, 'required_exp' => 300],
        ]);

        $this->refreshMstCache();
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_equipment_level')->where('rarity', self::RARITY)->delete();
        $this->refreshMstCache();
    }
}
