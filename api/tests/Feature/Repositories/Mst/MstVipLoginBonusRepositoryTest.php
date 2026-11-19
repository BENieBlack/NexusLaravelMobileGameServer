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
    public function 全日の報酬はday_id順で1クエリで取れる(): void
    {
        $this->makeBonus('vip_a', vipLevel: 1);
        $this->makeBonus('vip_b', vipLevel: 2);
        // id は入れた順（連番）。day を逆順に入れても day 昇順で返る
        $this->makeContent('vip_a', day: 2, contentMstId: 'a2');
        $this->makeContent('vip_a', day: 1, contentMstId: 'a1_second');
        $this->makeContent('vip_a', day: 1, contentMstId: 'a1_third');
        $this->makeContent('vip_b', day: 1, contentMstId: 'other');

        DB::connection('mst')->enableQueryLog();
        $rows = $this->repository->selectContentsByBonusId('vip_a');
        $queries = DB::connection('mst')->getQueryLog();
        DB::connection('mst')->disableQueryLog();

        $this->assertSame(['a1_second', 'a1_third', 'a2'], array_column($rows->all(), 'content_mst_id'));
        $this->assertCount(1, $queries, '全日の報酬は日ごとに引かず 1 クエリで取る');
    }

    #[Test]
    public function 一日分の報酬は登録順_idで並ぶ(): void
    {
        $this->makeBonus('vip_a', vipLevel: 1);
        $this->makeContent('vip_a', day: 1, contentMstId: 'z_first');
        $this->makeContent('vip_a', day: 1, contentMstId: 'a_second');

        $rows = $this->repository->selectContentsByBonusIdAndDay('vip_a', 1);

        $this->assertSame(['z_first', 'a_second'], array_column($rows->all(), 'content_mst_id'));
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

    private function makeContent(string $bonusId, int $day, string $contentMstId): void
    {
        DB::connection('mst')->table('mst_vip_login_bonus_content')->insert([
            'mst_vip_login_bonus_id' => $bonusId, 'day' => $day,
            'content_type' => 'item', 'content_mst_id' => $contentMstId,
            'content_quantity' => 1, 'amount' => 1,
        ]);
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_vip_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus')->delete();
    }
}
