<?php

namespace Tests\Feature\Seeders;

use App\Persistence\ApiSession;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MstItemSeeder;
use Database\Seeders\MstVipLoginBonusSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Resource\DataTransferObjects\Resource;
use Nexus\Core\Resource\Enums\ResourceType;
use NexusLogin\Services\LoginBonusOrchestrator;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * VIP ログインボーナスのシーダー（MstVipLoginBonusSeeder）のテスト。
 *
 * 以前は content_type が 'currency'（配布時に Resource::fromTypeString が ValueError）、
 * mst_item に無いアイテム ID を使っていたため、基本のシード（make seed）に入れられなかった。
 * 今は wallet / diamond / item と実在する ID を使い、DatabaseSeeder の Phase 1 から呼ぶ。
 *
 * シーダーは mst_vip_login_bonus* と mst_item を truncate する（暗黙コミット）ので、
 * トランザクションで包まず、触る行は tearDown で消す。
 */
class VipLoginBonusSeederTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const PLAYER_ID = 9302;

    public function beginDatabaseTransaction(): void
    {
        // シーダーの truncate が暗黙コミットになるため、自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();

        parent::tearDown();
    }

    #[Test]
    public function 基本のシードのphase1にvip_ログインボーナスが入っている(): void
    {
        // DatabaseSeeder は Phase 1 から呼ぶ。MstItemSeeder（アイテム ID の参照元）より後ろにあること
        $source = (string) file_get_contents((string) (new ReflectionClass(DatabaseSeeder::class))->getFileName());

        $this->assertStringContainsString('MstVipLoginBonusSeeder::class', $source);
        $this->assertLessThan(
            strpos($source, 'MstVipLoginBonusSeeder::class'),
            strpos($source, 'MstItemSeeder::class'),
        );
    }

    #[Test]
    public function vi_p0から10までの設定が7日分入り_amountは常に1になる(): void
    {
        $this->seed(MstVipLoginBonusSeeder::class);

        // 期待: VIP 0〜10 の 11 件、期間なし・有効・7 日ループ
        $bonuses = DB::connection('mst')->table('mst_vip_login_bonus')->orderBy('vip_level')->get();
        $this->assertSame(range(0, 10), $bonuses->pluck('vip_level')->map(fn ($v): int => (int) $v)->all());
        foreach ($bonuses as $bonus) {
            $this->assertSame(7, (int) $bonus->loop_days);
            $this->assertTrue((bool) $bonus->is_active);
            $this->assertNull($bonus->start_at);
            $this->assertNull($bonus->end_at);
        }

        // 期待: 各 VIP に 1〜7 日目の報酬があり、配布量は content_quantity、amount は倍率 1
        foreach ($bonuses as $bonus) {
            $days = DB::connection('mst')->table('mst_vip_login_bonus_content')
                ->where('mst_vip_login_bonus_id', $bonus->id)->distinct()->pluck('day')->map(fn ($d): int => (int) $d)->sort()->values()->all();
            $this->assertSame(range(1, 7), $days);
        }
        $this->assertSame(0, DB::connection('mst')->table('mst_vip_login_bonus_content')->where('amount', '!=', 1)->count());
        $this->assertSame(0, DB::connection('mst')->table('mst_vip_login_bonus_content')->where('content_quantity', '<', 1)->count());
    }

    #[Test]
    public function すべてのcontent_typeが_resourceに解釈でき_i_dが実在するマスターを指す(): void
    {
        $this->seed(MstItemSeeder::class);
        $this->seed(MstVipLoginBonusSeeder::class);

        $rows = DB::connection('mst')->table('mst_vip_login_bonus_content')->get();
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            // 配布時（convertToResource）と同じ規則: wallet のときは content_mst_id を ResourceType として使う
            $typeString = $row->content_type === 'wallet' ? $row->content_mst_id : $row->content_type;

            // 期待: ValueError にならない
            $resource = Resource::fromTypeString($typeString, $row->content_mst_id, (int) $row->content_quantity);
            $this->assertInstanceOf(Resource::class, $resource);

            // 期待: wallet と item は mst_item に実在する ID（wallet は type=currency の行）。ダイヤはダイヤの ID
            if ($row->content_type === 'wallet') {
                $this->assertTrue(
                    DB::connection('mst')->table('mst_item')->where('id', $row->content_mst_id)->where('type', 'currency')->exists(),
                    "wallet の {$row->content_mst_id} が mst_item に無い",
                );
            } elseif ($row->content_type === 'item') {
                $this->assertTrue(
                    DB::connection('mst')->table('mst_item')->where('id', $row->content_mst_id)->exists(),
                    "item の {$row->content_mst_id} が mst_item に無い",
                );
            } else {
                $this->assertSame(ResourceType::DIAMOND->value, $row->content_type);
                $this->assertSame('diamond', $row->content_mst_id);
            }
        }
    }

    #[Test]
    public function 冪等で2回流しても行数が変わらない(): void
    {
        $this->seed(MstVipLoginBonusSeeder::class);
        $bonus = DB::connection('mst')->table('mst_vip_login_bonus')->count();
        $content = DB::connection('mst')->table('mst_vip_login_bonus_content')->count();

        $this->seed(MstVipLoginBonusSeeder::class);

        $this->assertSame(11, $bonus);
        $this->assertSame($bonus, DB::connection('mst')->table('mst_vip_login_bonus')->count());
        $this->assertSame($content, DB::connection('mst')->table('mst_vip_login_bonus_content')->count());
    }

    #[Test]
    public function vi_p5のプレイヤーのloginでvipログインボーナスの1日目が配布される(): void
    {
        // 前提: シードしたマスターと、VIP5（5000pt）で trx1 のプレイヤー
        $this->seed(MstItemSeeder::class);
        $this->seed(MstVipLoginBonusSeeder::class);
        $this->createPlayer(vipPoint: 5000);
        ApiSession::clearForTest();
        ApiSession::setSysPlayerId(self::PLAYER_ID);

        // 操作: ログインボーナスを一括で受け取る（初回ログイン）
        $results = app(LoginBonusOrchestrator::class)->executeAllMerged(self::PLAYER_ID, null);

        // 期待: VIP5 の 1 日目（ゴールド 1000 x 2.5 = 2500）が配布される
        $this->assertCount(1, $results);
        $this->assertSame(ResourceType::GOLD, $results[0]->getType());
        $this->assertSame('gold', $results[0]->getId());
        $this->assertSame(2500, $results[0]->getAmount());
    }

    /** trx1 に割り当てた、指定の VIP ポイントのプレイヤーを作る */
    private function createPlayer(int $vipPoint): void
    {
        $shardingId = DB::connection('sys')->table('sys_sharding')->where('name', 'trx_sharding')->value('id');
        if (! $shardingId) {
            $shardingId = DB::connection('sys')->table('sys_sharding')->insertGetId([
                'name' => 'trx_sharding',
                'target' => 'transaction',
                'strategy' => 'hash',
                'sharding_key' => 'player_id',
                'node_count' => 2,
                'is_active' => true,
                'description' => 'Test sharding',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $nodeId = DB::connection('sys')->table('sys_sharding_node')
            ->where('sys_sharding_id', $shardingId)->where('node_no', 1)->value('id');
        if (! $nodeId) {
            $nodeId = DB::connection('sys')->table('sys_sharding_node')->insertGetId([
                'sys_sharding_id' => $shardingId,
                'node_name' => 'node1',
                'node_no' => 1,
                'weight' => 100,
                'status' => 'active',
                'is_writable' => true,
                'is_readable' => true,
                'max_connections' => 10000,
                'current_player_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('sys')->table('sys_player')->insert([
            'id' => self::PLAYER_ID,
            'uuid' => 'vip-seeder-uuid-'.self::PLAYER_ID,
            'my_id' => 'VIPSEED',
            'name' => 'Vip Seeder Player',
            'vip_point' => $vipPoint,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('sys')->table('sys_sharding_node_player')->insert([
            'sys_player_id' => self::PLAYER_ID,
            'sys_sharding_node_id' => $nodeId,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanUp(): void
    {
        DB::connection('trx1')->table('trx_vip_login_bonus')->where('sys_player_id', self::PLAYER_ID)->delete();
        DB::connection('trx1')->table('trx_wallet')->where('sys_player_id', self::PLAYER_ID)->delete();
        DB::connection('mst')->table('mst_vip_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_vip_login_bonus')->delete();
        DB::connection('mst')->table('mst_item')->delete();
        DB::connection('sys')->table('sys_sharding_node_player')->where('sys_player_id', self::PLAYER_ID)->delete();
        DB::connection('sys')->table('sys_player')->where('id', self::PLAYER_ID)->delete();
    }
}
