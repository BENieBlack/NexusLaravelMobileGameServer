<?php

namespace NexusResourceDelivery\Tests\Unit\Managers;

use Nexus\Core\Support\CustomCollection;
use NexusResource\DataTransferObjects\Resource;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryComplete;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\Managers\ResourceDeliveryManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ResourceDeliveryManager のユニットテスト
 *
 * 配送前リストと送信完了リストの出し入れを受け持つ。
 * 配送済みのものが配送前リストに残ると二重付与になるため、そこが要点。
 */
class ResourceDeliveryManagerTest extends TestCase
{
    #[Test]
    public function 最初は配送するものが無い(): void
    {
        $manager = new ResourceDeliveryManager;

        $this->assertFalse($manager->hasPendingContents());
        $this->assertTrue($manager->getPendingContents()->isEmpty());
    }

    #[Test]
    public function 追加したコンテンツは配送前リストに入る(): void
    {
        $manager = new ResourceDeliveryManager;
        $content = $this->content('item_potion');

        $manager->addContent($content);

        $this->assertTrue($manager->hasPendingContents());
        $this->assertSame($content, $manager->getPendingContents()->get($content->getUniqueId()));
    }

    #[Test]
    public function 数量が0以下のコンテンツは追加しない(): void
    {
        $manager = new ResourceDeliveryManager;

        $manager->addContent(ResourceDeliveryContent::fromResource(Resource::item('item_potion', 0)));

        $this->assertFalse($manager->hasPendingContents());
    }

    #[Test]
    public function まとめて追加できる(): void
    {
        $manager = new ResourceDeliveryManager;

        $manager->addContents(new CustomCollection([
            $this->content('item_potion'),
            ResourceDeliveryContent::fromResource(Resource::item('item_empty', 0)),
            $this->content('item_elixir'),
        ]));

        $this->assertCount(2, $manager->getPendingContents(), '無効なものは弾かれる');
    }

    #[Test]
    public function 送信完了にすると配送前リストから移る(): void
    {
        $manager = new ResourceDeliveryManager;
        $content = $this->content('item_potion');
        $manager->addContent($content);

        $manager->afterSend(new ResourceDeliveryComplete(new CustomCollection([$content])));

        $this->assertFalse($manager->hasPendingContents(), '配送済みが残ると二重付与になる');
        $this->assertSame(
            [$content],
            $manager->findSendCompleteContents(ResourceDeliveryContent::class)->values()->all()
        );
    }

    #[Test]
    public function 送信完了リストはクラスごとに分かれる(): void
    {
        $manager = new ResourceDeliveryManager;
        $content = $this->content('item_potion');
        $manager->addContent($content);
        $manager->afterSend(new ResourceDeliveryComplete(new CustomCollection([$content])));

        $this->assertTrue($manager->findSendCompleteContents(\stdClass::class)->isEmpty());
    }

    #[Test]
    public function 送信完了は積み上がる(): void
    {
        $manager = new ResourceDeliveryManager;

        foreach (['item_potion', 'item_elixir'] as $id) {
            $content = $this->content($id);
            $manager->addContent($content);
            $manager->afterSend(new ResourceDeliveryComplete(new CustomCollection([$content])));
        }

        $this->assertCount(2, $manager->findSendCompleteContents(ResourceDeliveryContent::class));
    }

    private function content(string $id): ResourceDeliveryContent
    {
        return ResourceDeliveryContent::fromResource(Resource::item($id, 1));
    }
}
