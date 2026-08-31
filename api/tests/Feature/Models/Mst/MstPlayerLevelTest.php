<?php

namespace Tests\Feature\Models\Mst;

use App\Models\Mst\MstPlayerLevel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * MstPlayerLevel のテスト
 *
 * プレイヤーのレベルと最大スタミナを決めるマスター。
 * 累積経験値からレベルを逆算するため、境界を誤ると
 * 上がるはずのレベルが上がらない／必要量を超えて上がる。
 */
class MstPlayerLevelTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
        $this->makeLevels();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    #[Test]
    public function レベルで引ける(): void
    {
        $level = MstPlayerLevel::findByLevel(3);

        $this->assertNotNull($level);
        $this->assertSame(3, $level->getLevel());
        $this->assertSame(250, $level->getRequiredExp());
        $this->assertSame(70, $level->getMaxStamina());
    }

    #[Test]
    public function 定義の無いレベルはnull(): void
    {
        $this->assertNull(MstPlayerLevel::findByLevel(99));
        $this->assertNull(MstPlayerLevel::findMaxStaminaForLevel(99));
        $this->assertNull(MstPlayerLevel::findRequiredExpForLevel(99));
    }

    #[Test]
    public function レベルから最大スタミナと必要経験値を引ける(): void
    {
        $this->assertSame(70, MstPlayerLevel::findMaxStaminaForLevel(3));
        $this->assertSame(250, MstPlayerLevel::findRequiredExpForLevel(3));
    }

    #[Test]
    public function 全レベルを取れる(): void
    {
        $this->assertCount(5, MstPlayerLevel::selectAllLevels());
    }

    #[Test]
    public function 最大レベルを取れる(): void
    {
        $this->assertSame(5, MstPlayerLevel::getMaxLevel());
    }

    // ========================================
    // 累積経験値からの逆算
    // ========================================

    #[Test]
    public function 累積経験値からレベルを求める(): void
    {
        // 1:0, 2:100, 3:250, 4:400, 5:600
        $this->assertSame(1, MstPlayerLevel::calculateLevelFromExp(0));
        $this->assertSame(1, MstPlayerLevel::calculateLevelFromExp(99));
        $this->assertSame(2, MstPlayerLevel::calculateLevelFromExp(100), '必要量ちょうどで上がる');
        $this->assertSame(2, MstPlayerLevel::calculateLevelFromExp(249));
        $this->assertSame(3, MstPlayerLevel::calculateLevelFromExp(250));
    }

    #[Test]
    public function 最大レベルを超える経験値でも最大レベルで止まる(): void
    {
        $this->assertSame(5, MstPlayerLevel::calculateLevelFromExp(999999));
    }

    #[Test]
    public function 経験値が負でもレベル1になる(): void
    {
        // 計算を誤って負が渡っても、0未満のレベルは作らない
        $this->assertSame(1, MstPlayerLevel::calculateLevelFromExp(-1));
    }

    #[Test]
    public function レスポンス用の配列にレベルが載る(): void
    {
        // 主キーがlevelでidを持たないため、置き換えは起きない
        $array = MstPlayerLevel::findByLevel(3)?->toResponseArray() ?? [];

        $this->assertSame(3, $array['level']);
        $this->assertArrayNotHasKey('id', $array);
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

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_player_level')->delete();
        $this->refreshMstCache();
    }
}
