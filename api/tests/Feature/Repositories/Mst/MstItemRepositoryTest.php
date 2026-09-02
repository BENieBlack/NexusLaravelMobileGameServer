<?php

namespace Tests\Feature\Repositories\Mst;

use App\Models\Mst\MstItem;
use App\Repositories\Mst\MstItemRepository;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * MstItemRepository のテスト
 *
 * アイテムを trx_item で持つか Wallet の残高として持つかは
 * mst_item.is_wallet で決まる。ItemService の振り分けがここを見ている。
 *
 * selectWalletManaged() は wallet:migrate-items が使っていたが、
 * マスターキャッシュを迂回するためコマンドが mst を直読みするようになり、
 * 現在は呼び出し元が無い。使うときに壊れていないよう固定しておく。
 */
class MstItemRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private MstItemRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
        $this->repository = app(MstItemRepository::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    #[Test]
    public function wallet管理かどうかをマスターから判定する(): void
    {
        $this->makeItem('gold', isWallet: true);
        $this->makeItem('item_potion', isWallet: false);

        $this->assertTrue($this->repository->isWalletManaged('gold'));
        $this->assertFalse($this->repository->isWalletManaged('item_potion'));
    }

    #[Test]
    public function マスターに無いidはwallet管理ではない(): void
    {
        // 未定義のIDを残高側へ流すと、通貨として扱われてしまう
        $this->assertFalse($this->repository->isWalletManaged('no_such_item'));
    }

    #[Test]
    public function wallet管理のアイテムだけを集められる(): void
    {
        $this->makeItem('gold', isWallet: true);
        $this->makeItem('coin', isWallet: true);
        $this->makeItem('item_potion', isWallet: false);

        $ids = $this->repository->selectWalletManaged()
            ->map(fn (MstItem $item) => $item->getAttribute('id'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['coin', 'gold'], $ids);
    }

    #[Test]
    public function wallet管理が無ければ空で返る(): void
    {
        $this->makeItem('item_potion', isWallet: false);

        $this->assertCount(0, $this->repository->selectWalletManaged());
    }

    #[Test]
    public function idでマスターを引ける(): void
    {
        $this->makeItem('item_potion', isWallet: false);

        $item = $this->repository->selectById('item_potion');

        $this->assertNotNull($item);
        $this->assertSame('Recovery', $item->getAttribute('type'));
        $this->assertFalse($item->isWallet());
    }

    private function makeItem(string $id, bool $isWallet): void
    {
        DB::connection('mst')->table('mst_item')->insert([
            'id' => $id,
            'type' => $isWallet ? 'Currency' : 'Recovery',
            'effect' => $isWallet ? 'None' : 'HealHP',
            'value' => 0,
            'is_wallet' => $isWallet,
        ]);

        $this->refreshMstCache();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_item')
            ->whereIn('id', ['gold', 'coin', 'item_potion'])->delete();

        $this->refreshMstCache();
    }
}
