<?php

namespace Tests\Feature\Repositories\Mst;

use App\Repositories\Mst\MstVipLevelRepository;
use App\Repositories\Mst\MstVipLevelRewardRepository;
use Illuminate\Support\Facades\DB;
use NexusVip\Models\MstVipLevel;
use NexusVip\Models\MstVipLevelReward;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * VIPマスターのRepositoryのテスト
 *
 * 累積VIPポイントからレベルを引くところと、レベルアップで
 * まとめて配る報酬を引くところ。無効化した行を混ぜないこと、
 * レベルの範囲指定の境界（開始は含まない・終了は含む）が要点。
 */
class VipMasterRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private MstVipLevelRepository $levelRepository;

    private MstVipLevelRewardRepository $rewardRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
        $this->levelRepository = app(MstVipLevelRepository::class);
        $this->rewardRepository = app(MstVipLevelRewardRepository::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    // ========================================
    // MstVipLevelRepository
    // ========================================

    #[Test]
    public function レベル番号で引ける(): void
    {
        $this->makeLevels();

        $level = $this->levelRepository->selectByLevel(2);

        $this->assertNotNull($level);
        $this->assertSame(2, $level->getLevel());
        $this->assertSame(500, $level->getRequiredPoint());
    }

    #[Test]
    public function 無効なレベルは引けない(): void
    {
        $this->makeLevels();
        $this->makeLevel(9, requiredPoint: 99999, isActive: false);

        $this->assertNull($this->levelRepository->selectByLevel(9));
    }

    #[Test]
    public function 有効なレベルだけが必要ポイント順で返る(): void
    {
        $this->makeLevels();
        $this->makeLevel(9, requiredPoint: 50, isActive: false);

        $levels = $this->levelRepository->selectAllLevels();

        $this->assertSame(
            [0, 1, 2, 3],
            $levels->map(fn (MstVipLevel $level) => $level->getLevel())->all()
        );
    }

    #[Test]
    public function レベル昇順でも取れる(): void
    {
        $this->makeLevels();

        $this->assertSame(
            [0, 1, 2, 3],
            $this->levelRepository->selectAllActiveOrderByLevel()
                ->map(fn (MstVipLevel $level) => $level->getLevel())->all()
        );
    }

    #[Test]
    public function 累積ポイントから到達レベルを求める(): void
    {
        // 0pt=VIP0, 100pt=VIP1, 500pt=VIP2, 1000pt=VIP3
        $this->makeLevels();

        $this->assertSame(0, $this->levelRepository->selectMaxLevelByPoints(0)->getLevel());
        $this->assertSame(0, $this->levelRepository->selectMaxLevelByPoints(99)->getLevel());
        $this->assertSame(1, $this->levelRepository->selectMaxLevelByPoints(100)->getLevel(), '必要ポイントちょうどで上がる');
        $this->assertSame(1, $this->levelRepository->selectMaxLevelByPoints(499)->getLevel());
        $this->assertSame(2, $this->levelRepository->selectMaxLevelByPoints(500)->getLevel());
        $this->assertSame(3, $this->levelRepository->selectMaxLevelByPoints(99999)->getLevel(), '最大レベルで止まる');
    }

    #[Test]
    public function 到達レベルの判定に無効なレベルは使わない(): void
    {
        $this->makeLevels();
        $this->makeLevel(9, requiredPoint: 200, isActive: false);

        $this->assertSame(1, $this->levelRepository->selectMaxLevelByPoints(300)->getLevel());
    }

    #[Test]
    public function vip0が無ければ例外になる(): void
    {
        // 到達レベルが引けないとき VIP0 に落とすが、それも無ければ黙って進めない
        $this->makeLevel(1, requiredPoint: 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VIP level 0 not found in master data');

        $this->levelRepository->selectMaxLevelByPoints(0);
    }

    #[Test]
    public function idで引ける(): void
    {
        $this->makeLevels();

        $this->assertSame(2, $this->levelRepository->selectById('vip_2')?->getLevel());
        $this->assertNull($this->levelRepository->selectById('vip_999'));
    }

    // ========================================
    // MstVipLevelRewardRepository
    // ========================================

    #[Test]
    public function レベルの報酬をソート順で取れる(): void
    {
        $this->makeReward(vipLevel: 1, contentMstId: 'item_b', sortOrder: 2);
        $this->makeReward(vipLevel: 1, contentMstId: 'item_a', sortOrder: 1);
        $this->makeReward(vipLevel: 2, contentMstId: 'item_c', sortOrder: 1);

        $this->assertSame(
            ['item_a', 'item_b'],
            $this->rewardRepository->selectByVipLevel(1)
                ->map(fn (MstVipLevelReward $reward) => $reward->getContentMstId())->all()
        );
    }

    #[Test]
    public function 無効な報酬も含めて取れる(): void
    {
        $this->makeReward(vipLevel: 1, contentMstId: 'item_a', sortOrder: 1);
        $this->makeReward(vipLevel: 1, contentMstId: 'item_off', sortOrder: 2, isActive: false);

        $this->assertCount(2, $this->rewardRepository->selectByVipLevel(1));
        $this->assertCount(1, $this->rewardRepository->selectActiveByVipLevel(1), '有効なものだけに絞れる');
    }

    #[Test]
    public function レベルの範囲で報酬をまとめて取れる(): void
    {
        // 開始レベルは含まず、終了レベルは含む。
        // VIP1からVIP3へ上がったら、2と3の報酬だけを配る
        foreach ([1, 2, 3, 4] as $level) {
            $this->makeReward(vipLevel: $level, contentMstId: "item_{$level}", sortOrder: 1);
        }

        $rewards = $this->rewardRepository->selectActiveByLevelRange(1, 3);

        $this->assertSame(
            ['item_2', 'item_3'],
            $rewards->map(fn (MstVipLevelReward $reward) => $reward->getContentMstId())->all()
        );
    }

    #[Test]
    public function 範囲指定でも無効な報酬は外れる(): void
    {
        $this->makeReward(vipLevel: 2, contentMstId: 'item_a', sortOrder: 1);
        $this->makeReward(vipLevel: 2, contentMstId: 'item_off', sortOrder: 2, isActive: false);

        $this->assertCount(1, $this->rewardRepository->selectActiveByLevelRange(1, 3));
    }

    #[Test]
    public function レベルが上がっていなければ報酬は無い(): void
    {
        $this->makeReward(vipLevel: 2, contentMstId: 'item_a', sortOrder: 1);

        $this->assertCount(0, $this->rewardRepository->selectActiveByLevelRange(2, 2));
    }

    #[Test]
    public function 有効な報酬を全件取れる(): void
    {
        $this->makeReward(vipLevel: 2, contentMstId: 'item_2', sortOrder: 1);
        $this->makeReward(vipLevel: 1, contentMstId: 'item_1', sortOrder: 1);
        $this->makeReward(vipLevel: 3, contentMstId: 'item_off', sortOrder: 1, isActive: false);

        $this->assertSame(
            ['item_1', 'item_2'],
            $this->rewardRepository->selectAllActive()
                ->map(fn (MstVipLevelReward $reward) => $reward->getContentMstId())->all()
        );
    }

    #[Test]
    public function 報酬が無ければ空で返る(): void
    {
        $this->assertCount(0, $this->rewardRepository->selectByVipLevel(1));
        $this->assertCount(0, $this->rewardRepository->selectAllActive());
    }

    private function makeLevels(): void
    {
        $this->makeLevel(0, requiredPoint: 0);
        $this->makeLevel(1, requiredPoint: 100);
        $this->makeLevel(2, requiredPoint: 500);
        $this->makeLevel(3, requiredPoint: 1000);
    }

    private function makeLevel(int $level, int $requiredPoint, bool $isActive = true): void
    {
        DB::connection('mst')->table('mst_vip_level')->insert([
            'id' => "vip_{$level}",
            'level' => $level,
            'required_point' => $requiredPoint,
            'max_stamina_bonus' => $level * 10,
            'daily_diamond_bonus' => $level * 5,
            'shop_discount_rate' => 0,
            'gacha_discount_rate' => 0,
            'sort_desc' => $level,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function makeReward(
        int $vipLevel,
        string $contentMstId,
        int $sortOrder,
        bool $isActive = true,
    ): void {
        DB::connection('mst')->table('mst_vip_level_reward')->insert([
            'vip_level' => $vipLevel,
            'content_type' => 'item',
            'content_mst_id' => $contentMstId,
            'content_quantity' => 1,
            'amount' => 1,
            'is_paid' => false,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_vip_level_reward')->delete();
        DB::connection('mst')->table('mst_vip_level')->delete();
        $this->refreshMstCache();
    }
}
