<?php

namespace Tests\Feature\Repositories\Mst;

use App\Domain\InAppPurchase\Constants\InAppPurchaseConst;
use App\Models\Mst\MstInAppPurchase;
use App\Repositories\Mst\MstInAppPurchaseRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * MstInAppPurchaseRepository のテスト
 *
 * ストアに並べる商品を引く層。無効化した商品が並ぶと
 * 販売停止したものを買えてしまうため、is_active の扱いが要点。
 *
 * 並び順は sort_desc の降順で、運営が並べ替えた通りに出す必要がある。
 */
class MstInAppPurchaseRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private MstInAppPurchaseRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
        $this->repository = app(MstInAppPurchaseRepository::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    #[Test]
    public function 有効な商品だけを並び順で返す(): void
    {
        $this->makeProduct(1, InAppPurchaseConst::TYPE_DIAMOND, sortDesc: 10);
        $this->makeProduct(2, InAppPurchaseConst::TYPE_PACK, sortDesc: 30);
        $this->makeProduct(3, InAppPurchaseConst::TYPE_PASS, sortDesc: 20);
        $this->makeProduct(4, InAppPurchaseConst::TYPE_DIAMOND, sortDesc: 99, isActive: false);

        $ids = $this->repository->selectAllActive()
            ->map(fn (MstInAppPurchase $product) => $product->getId())->all();

        $this->assertSame([2, 3, 1], $ids, 'sort_descの降順。無効な商品は並ばない');
    }

    #[Test]
    public function タイプで絞り込める(): void
    {
        $this->makeProduct(1, InAppPurchaseConst::TYPE_DIAMOND, sortDesc: 10);
        $this->makeProduct(2, InAppPurchaseConst::TYPE_PACK, sortDesc: 20);
        $this->makeProduct(3, InAppPurchaseConst::TYPE_DIAMOND, sortDesc: 30);

        $ids = $this->repository->selectAllActiveByType(InAppPurchaseConst::TYPE_DIAMOND)
            ->map(fn (MstInAppPurchase $product) => $product->getId())->all();

        $this->assertSame([3, 1], $ids);
    }

    #[Test]
    public function タイプで絞っても無効な商品は出さない(): void
    {
        $this->makeProduct(1, InAppPurchaseConst::TYPE_PACK, isActive: false);

        $this->assertCount(0, $this->repository->selectAllActiveByType(InAppPurchaseConst::TYPE_PACK));
    }

    #[Test]
    public function 商品が無ければ空で返る(): void
    {
        $this->assertCount(0, $this->repository->selectAllActive());
        $this->assertCount(0, $this->repository->selectAllActiveByType(InAppPurchaseConst::TYPE_PACK));
    }

    #[Test]
    public function idで有効な商品を引ける(): void
    {
        $this->makeProduct(1, InAppPurchaseConst::TYPE_PACK);

        $this->assertSame(1, $this->repository->selectActiveById(1)?->getId());
    }

    #[Test]
    public function 販売停止した商品はidでも引けない(): void
    {
        // ここで引けると、ストアから消したはずの商品を買えてしまう
        $this->makeProduct(1, InAppPurchaseConst::TYPE_PACK, isActive: false);

        $this->assertNull($this->repository->selectActiveById(1));
    }

    #[Test]
    public function 存在しないidはnull(): void
    {
        $absentId = $this->nonExistentId('mst_in_app_purchase', 'mst');

        $this->assertNull($this->repository->selectActiveById($absentId));
        $this->assertNull($this->repository->selectByIdWithRelations($absentId));
    }

    #[Test]
    public function 中身と効果を一緒に引ける(): void
    {
        // 購入処理は contents と effects を辿るため、まとめて読む
        $this->makeProduct(1, InAppPurchaseConst::TYPE_PACK);
        $this->makeContent(1, InAppPurchaseConst::CONTENT_TYPE_ITEM, 'item_potion');
        $this->makeEffect(1, InAppPurchaseConst::EFFECT_TYPE_EXP_BOOST);

        $product = $this->repository->selectByIdWithRelations(1);

        $this->assertNotNull($product);
        $this->assertTrue($product->relationLoaded('contents'));
        $this->assertTrue($product->relationLoaded('effects'));
        $this->assertCount(1, $product->contents);
        $this->assertCount(1, $product->effects);
    }

    #[Test]
    public function 中身が無い商品も引ける(): void
    {
        $this->makeProduct(1, InAppPurchaseConst::TYPE_DIAMOND);

        $product = $this->repository->selectByIdWithRelations(1);

        $this->assertCount(0, $product->contents);
        $this->assertCount(0, $product->effects);
    }

    #[Test]
    public function 販売停止でもリレーション付きでは引ける(): void
    {
        // 過去の購入履歴の表示など、停止後も参照する経路がある
        $this->makeProduct(1, InAppPurchaseConst::TYPE_PACK, isActive: false);

        $this->assertNotNull($this->repository->selectByIdWithRelations(1));
    }

    private function makeProduct(
        int $id,
        string $type,
        int $sortDesc = 1,
        bool $isActive = true,
    ): void {
        DB::connection('mst')->table('mst_in_app_purchase')->insert([
            'id' => $id,
            'type' => $type,
            'paid_diamond_amount' => 100,
            'vip_point' => 98,
            'purchase_limit_reset' => InAppPurchaseConst::PURCHASE_LIMIT_RESET_NONE,
            'sort_desc' => $sortDesc,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function makeContent(int $productId, string $contentType, string $contentMstId): void
    {
        DB::connection('mst')->table('mst_in_app_purchase_content')->insert([
            'mst_in_app_purchase_id' => $productId,
            'content_type' => $contentType,
            'content_mst_id' => $contentMstId,
            'content_quantity' => 1,
            'amount' => 1,
            'sort_desc' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function makeEffect(int $productId, string $effectType): void
    {
        DB::connection('mst')->table('mst_in_app_purchase_effect')->insert([
            'mst_in_app_purchase_id' => $productId,
            'effect_type' => $effectType,
            'value' => 1.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_in_app_purchase_content')->delete();
        DB::connection('mst')->table('mst_in_app_purchase_effect')->delete();
        DB::connection('mst')->table('mst_in_app_purchase')->delete();
        $this->refreshMstCache();
    }
}
