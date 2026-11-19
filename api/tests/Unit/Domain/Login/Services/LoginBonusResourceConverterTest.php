<?php

namespace Tests\Unit\Domain\Login\Services;

use Nexus\Core\Resource\Enums\ResourceType;
use NexusLogin\Services\LoginBonusResourceConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LoginBonusResourceConverter のテスト
 *
 * 配布・カレンダー表示・再受け取りが共有する「報酬の行 → Resource」の変換規則を固定する。
 */
class LoginBonusResourceConverterTest extends TestCase
{
    #[Test]
    public function 配る量は_content_quantityとamountの積になる(): void
    {
        $content = LoginBonusResourceConverter::toContent([
            'content_type' => 'item', 'content_mst_id' => 'potion', 'content_quantity' => 5, 'amount' => 3,
        ]);

        $resource = LoginBonusResourceConverter::toResource($content);

        $this->assertSame(ResourceType::ITEM, $resource->getType());
        $this->assertSame('potion', $resource->getId());
        $this->assertSame(15, $resource->getAmount());
        $this->assertNull($resource->getMetadata());
    }

    #[Test]
    public function walletはcontent_mst_idがtypeになる(): void
    {
        $content = LoginBonusResourceConverter::toContent([
            'content_type' => 'wallet', 'content_mst_id' => 'gold', 'content_quantity' => 100, 'amount' => 1,
        ]);

        $this->assertSame(ResourceType::GOLD, LoginBonusResourceConverter::toResource($content)->getType());
    }

    #[Test]
    public function 有償フラグとcontent_optionはmetadataに入る(): void
    {
        $content = LoginBonusResourceConverter::toContent([
            'content_type' => 'unit', 'content_mst_id' => 'u1', 'content_quantity' => 1, 'amount' => 1,
            'is_paid' => true, 'content_option' => ['grade' => 2],
        ]);

        $this->assertSame(
            ['is_paid' => true, 'grade' => 2],
            LoginBonusResourceConverter::toResource($content)->getMetadata()
        );
    }

    #[Test]
    public function 未知のcontent_typeは_value_errorになる(): void
    {
        $content = LoginBonusResourceConverter::toContent([
            'content_type' => 'no_such_type', 'content_mst_id' => 'x', 'content_quantity' => 1, 'amount' => 1,
        ]);

        $this->expectException(\ValueError::class);

        LoginBonusResourceConverter::toResource($content);
    }

    #[Test]
    public function 複数の行をまとめて変換でき_順序を保つ(): void
    {
        $contents = LoginBonusResourceConverter::toContents([
            ['content_type' => 'item', 'content_mst_id' => 'b', 'content_quantity' => 1, 'amount' => 1],
            ['content_type' => 'item', 'content_mst_id' => 'a', 'content_quantity' => 1, 'amount' => 1],
        ]);

        $this->assertSame(['b', 'a'], $contents->map(fn ($c) => $c->getContentMstId())->all());
    }
}
