<?php

namespace Tests\Feature\Repositories\Mst;

use App\Repositories\Mst\MstVipLoginBonusRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * MstVipLoginBonusRepository のテスト
 *
 * 配布・カレンダー・再受け取りが同じ報酬の並び・同じボーナス 1 件を引くための「並びの固定」を見る。
 */
class MstVipLoginBonusRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private MstVipLoginBonusRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
        $this->repository = app(MstVipLoginBonusRepository::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $this->refreshMstCache();

        parent::tearDown();
    }

    #[Test]
    public function 一日分の報酬はsort_descの降順で並び_同値はid昇順(): void
    {
        $this->makeBonus('vip_a', vipLevel: 1);
        // id は入れた順（連番）。sort_desc が大きいものが先、同値（0）は id 昇順
        $this->makeContent('vip_a', day: 1, contentMstId: 'low', sortDesc: 1);
        $this->makeContent('vip_a', day: 1, contentMstId: 'tie_first');
        $this->makeContent('vip_a', day: 1, contentMstId: 'high', sortDesc: 9);
        $this->makeContent('vip_a', day: 1, contentMstId: 'tie_second');
        $this->makeContent('vip_a', day: 2, contentMstId: 'other_day', sortDesc: 99);

        $rows = $this->repository->selectContentsByBonusIdAndDay('vip_a', 1);

        $this->assertSame(['high', 'low', 'tie_first', 'tie_second'], array_column($rows->all(), 'content_mst_id'));
    }

    #[Test]
    public function 同じレベルに有効な行が複数あっても_idの小さい1件に固定される(): void
    {
        $this->makeBonus('vip_z', vipLevel: 3);
        $this->makeBonus('vip_a', vipLevel: 3);

        $this->assertSame('vip_a', $this->repository->selectActiveByVipLevel(3)['id']);
    }

    private function makeBonus(string $id, int $vipLevel): void
    {
        DB::connection('mst')->table('mst_vip_login_bonus')->insert([
            'id' => $id, 'vip_level' => $vipLevel, 'loop_days' => 7, 'is_active' => true,
        ]);
        $this->refreshMstCache();
    }

    private function makeContent(string $bonusId, int $day, string $contentMstId, int $sortDesc = 0): void
    {
        DB::connection('mst')->table('mst_vip_login_bonus_content')->insert([
            'mst_vip_login_bonus_id' => $bonusId, 'day' => $day,
            'content_type' => 'item', 'content_mst_id' => $contentMstId,
            'content_quantity' => 1, 'amount' => 1, 'sort_desc' => $sortDesc,
        ]);
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_vip_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus')->delete();
    }
}
