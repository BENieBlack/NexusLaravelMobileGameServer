<?php

namespace Tests\Feature\Album;

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusAlbum\Enums\AlbumContentType;
use NexusAlbum\Services\AlbumService;
use NexusResource\DataTransferObjects\Resource;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * アルバム（収集記録）のテスト
 *
 * 一度でも入手した対象を記録し、手放しても残ることが要点。
 * 記録は配布経由（Handlerが包んでいる）と直接呼び出しの両方から入る。
 */
class AlbumEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private string $accessToken;

    public function beginDatabaseTransaction(): void
    {
        // 配布がQueryManagerでフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-08-28 12:00:00');

        $this->cleanUpMaster();
        $this->createMaster();
        $this->refreshMstCache();

        ['player' => $player, 'token' => $token] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        $this->accessToken = $token;

        ApiSession::setSysPlayerId($this->sysPlayerId);
        app(QueryManager::class)->clear();
    }

    protected function tearDown(): void
    {
        foreach (['trx1', 'trx2'] as $connection) {
            DB::connection($connection)->table('trx_album')
                ->where('sys_player_id', $this->sysPlayerId)->delete();
        }

        $this->cleanUpMaster();
        // マスターキャッシュに自分が入れた行が残ったままだと、後続のテストが
        // 自前で入れたマスターを引けなくなる
        $this->refreshMstCache();
        ApiSession::clearForTest();
        ClockUtility::reset();
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function 記録が無ければ空の一覧と0件の収集状況を返す(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->accessToken))
            ->getJson('/api/album/list');

        $response->assertOk();
        $this->assertSame([], $response->json('album_entry_array'));

        $progressByType = $this->indexProgressByType($response->json('progress_array'));

        $this->assertSame(0, $progressByType['unit']['unlocked_count']);
        $this->assertSame(2, $progressByType['unit']['total_count'], 'アルバム対象のユニット数');
    }

    #[Test]
    public function 直接解放した対象が一覧に載る(): void
    {
        $this->albumService()->unlock($this->sysPlayerId, AlbumContentType::UNIT, 'unit_knight_001');
        $this->flush();

        $response = $this->withHeaders($this->authHeaders($this->accessToken))
            ->getJson('/api/album/list');

        $response->assertOk();
        $response->assertJsonPath('album_entry_array.0.content_type', 'unit');
        $response->assertJsonPath('album_entry_array.0.content_mst_id', 'unit_knight_001');
        $response->assertJsonPath('album_entry_array.0.unlocked_at', '2026-08-28 12:00:00');
    }

    #[Test]
    public function 配布で入手した対象が記録される(): void
    {
        $this->deliver([
            Resource::unit('unit_knight_001', 1),
            Resource::item('item_collectible_001', 3),
        ]);

        $this->assertSame(
            [['item', 'item_collectible_001'], ['unit', 'unit_knight_001']],
            $this->findEntries(),
            '配布経由でもアルバムに記録される'
        );
    }

    #[Test]
    public function アルバム対象外のアイテムは記録されない(): void
    {
        $this->deliver([Resource::item('item_potion_001', 1)]);

        $this->assertSame([], $this->findEntries(), '消耗品は is_album_target=false のため載らない');
    }

    #[Test]
    public function 同じ対象を二度入手しても記録は1件(): void
    {
        $this->deliver([Resource::unit('unit_knight_001', 1)]);
        $this->deliver([Resource::unit('unit_knight_001', 1)]);

        $this->assertSame([['unit', 'unit_knight_001']], $this->findEntries());
    }

    #[Test]
    public function 手放しても記録は残る(): void
    {
        $this->deliver([Resource::unit('unit_knight_001', 1)]);

        // 所持ユニットを消しても、アルバムの記録には影響しない
        DB::connection($this->playerConnection($this->sysPlayerId))
            ->table('trx_unit')->where('sys_player_id', $this->sysPlayerId)->delete();

        $this->assertSame([['unit', 'unit_knight_001']], $this->findEntries());
    }

    #[Test]
    public function 収集状況が入手に応じて進む(): void
    {
        $this->deliver([Resource::unit('unit_knight_001', 1)]);

        $response = $this->withHeaders($this->authHeaders($this->accessToken))
            ->getJson('/api/album/list');

        $progressByType = $this->indexProgressByType($response->json('progress_array'));

        $this->assertSame(1, $progressByType['unit']['unlocked_count']);
        $this->assertSame(2, $progressByType['unit']['total_count']);
        $this->assertSame(0.5, (float) $progressByType['unit']['rate']);
        $this->assertFalse($progressByType['unit']['is_complete']);
    }

    #[Test]
    public function 全て集めるとcompleteになる(): void
    {
        $this->deliver([
            Resource::unit('unit_knight_001', 1),
            Resource::unit('unit_mage_002', 1),
        ]);

        $response = $this->withHeaders($this->authHeaders($this->accessToken))
            ->getJson('/api/album/list');

        $progressByType = $this->indexProgressByType($response->json('progress_array'));

        $this->assertTrue($progressByType['unit']['is_complete']);
        $this->assertSame(1.0, (float) $progressByType['unit']['rate']);
    }

    #[Test]
    public function 認証が無ければ取得できない(): void
    {
        $this->getJson('/api/album/list')->assertStatus(401);
    }

    /**
     * @param  array<int, resource>  $resources
     */
    private function deliver(array $resources): void
    {
        $deliveryService = app(ResourceDeliveryService::class);
        $deliveryService->addResources($resources);
        $deliveryService->deliver($this->sysPlayerId);
        $this->flush();
    }

    private function albumService(): AlbumService
    {
        return app(AlbumService::class);
    }

    private function flush(): void
    {
        app(QueryManager::class)->flush();
    }

    /**
     * 記録されている対象を [種別, マスターID] の配列で返す（順序を固定）
     *
     * @return list<array{0: string, 1: string}>
     */
    private function findEntries(): array
    {
        $rows = DB::connection($this->playerConnection($this->sysPlayerId))
            ->table('trx_album')
            ->where('sys_player_id', $this->sysPlayerId)
            ->get();

        // content_type は ENUM なので ORDER BY が宣言順になる。比較しやすいようPHP側で並べる
        $entries = $rows->map(fn ($row) => [$row->content_type, $row->content_mst_id])->all();
        sort($entries);

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $progressArray
     * @return array<string, array<string, mixed>>
     */
    private function indexProgressByType(array $progressArray): array
    {
        $indexed = [];

        foreach ($progressArray as $progress) {
            $indexed[$progress['content_type']] = $progress;
        }

        return $indexed;
    }

    private function createMaster(): void
    {
        DB::connection('mst')->table('mst_unit')->insert([
            ['id' => 'unit_knight_001', 'type' => 'attack', 'element' => 'fire', 'rarity' => 'R', 'is_album_target' => true],
            ['id' => 'unit_mage_002', 'type' => 'attack', 'element' => 'water', 'rarity' => 'SR', 'is_album_target' => true],
        ]);

        DB::connection('mst')->table('mst_item')->insert([
            // 収集対象のアイテム
            ['id' => 'item_collectible_001', 'type' => 'material', 'effect' => 'none', 'value' => 0, 'is_album_target' => true],
            // 消耗品はアルバムに載せない
            ['id' => 'item_potion_001', 'type' => 'consumable', 'effect' => 'none', 'value' => 0, 'is_album_target' => false],
        ]);
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_unit')->delete();
        DB::connection('mst')->table('mst_item')->delete();
        DB::connection('mst')->table('mst_equipment')->delete();
    }
}
