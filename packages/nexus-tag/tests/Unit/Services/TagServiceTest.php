<?php

namespace NexusTag\Tests\Unit\Services;

use NexusTag\DataTransferObjects\TagContent;
use NexusTag\Repositories\TagRepositoryInterface;
use NexusTag\Services\TagService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TagServiceのユニットテスト
 *
 * 1つの対象が複数のタグを持ち、1つのタグが複数の対象に付く（多対多）ことと、
 * 種別が違えば同じIDでも別の対象として扱うことを固定する。
 */
class TagServiceTest extends TestCase
{
    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TagService(new FakeTagRepository([
            new TagContent('tag_series_v_gundam', 'unit', 'unit_v_gundam'),
            new TagContent('tag_type_gundam', 'unit', 'unit_v_gundam'),
            new TagContent('tag_series_v_gundam', 'unit', 'unit_v2_gundam'),
            new TagContent('tag_type_gundam', 'unit', 'unit_rx_78'),
            // ユニットと同じIDのアイテムがあっても混ざらない
            new TagContent('tag_series_v_gundam', 'item', 'unit_rx_78'),
        ]));
    }

    #[Test]
    public function 対象に付いたタグを全部返す(): void
    {
        $this->assertSame(
            ['tag_series_v_gundam', 'tag_type_gundam'],
            $this->service->findTagIds('unit', 'unit_v_gundam'),
        );
    }

    #[Test]
    public function 種別が違う紐付けは含めない(): void
    {
        $this->assertSame(['tag_type_gundam'], $this->service->findTagIds('unit', 'unit_rx_78'));
    }

    #[Test]
    public function タグが無い対象は空配列(): void
    {
        $this->assertSame([], $this->service->findTagIds('unit', 'unit_zaku'));
    }

    #[Test]
    public function 指定したタグを持つかを判定できる(): void
    {
        $this->assertTrue($this->service->hasTag('unit', 'unit_v_gundam', 'tag_series_v_gundam'));
        $this->assertFalse($this->service->hasTag('unit', 'unit_rx_78', 'tag_series_v_gundam'));
    }

    #[Test]
    public function タグを持つ対象を種別で絞って返す(): void
    {
        $this->assertSame(
            ['unit_v_gundam', 'unit_v2_gundam'],
            $this->service->findContentMstIds('tag_series_v_gundam', 'unit'),
        );
        $this->assertSame(['unit_rx_78'], $this->service->findContentMstIds('tag_series_v_gundam', 'item'));
    }

    #[Test]
    public function 紐付けの無いタグは空配列(): void
    {
        $this->assertSame([], $this->service->findContentMstIds('tag_series_wing', 'unit'));
    }
}

/**
 * テスト用のTagRepository
 */
class FakeTagRepository implements TagRepositoryInterface
{
    /**
     * @param  array<int, TagContent>  $tagContents
     */
    public function __construct(
        private readonly array $tagContents,
    ) {}

    public function selectAllContents(): array
    {
        return $this->tagContents;
    }
}
