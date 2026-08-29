<?php

namespace NexusResourceDelivery\Tests\Unit\DataTransferObjects;

use Nexus\Core\Support\CustomCollection;
use NexusResource\DataTransferObjects\Resource;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliverySummary;
use NexusResourceDelivery\Enums\ResourceDeliveryResultReason;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ResourceDeliverySummary のユニットテスト
 *
 * 配送結果のまとめ。上限超過の判定結果でポリシーが例外を投げるかが決まるため、
 * 「どのタイプを対象にすると超過扱いになるか」が要点。
 */
class ResourceDeliverySummaryDtoTest extends TestCase
{
    #[Test]
    public function 最初は空(): void
    {
        $summary = new ResourceDeliverySummary;

        $this->assertSame(0, $summary->getTotalCount());
        $this->assertTrue($summary->getContents()->isEmpty());
    }

    #[Test]
    public function コンテンツを追加すると件数が増える(): void
    {
        $summary = new ResourceDeliverySummary;

        $summary->addContents($this->contents(['item_potion', 'item_elixir']));
        $summary->addContents($this->contents(['item_ether']));

        $this->assertSame(3, $summary->getTotalCount());
    }

    #[Test]
    public function キーが重なっても件数は減らない(): void
    {
        // Managerはコンテンツを uniqueId をキーにした連想配列で持つ。
        // キーを保ったままmergeすると上書きで消えてしまう
        $summary = new ResourceDeliverySummary;

        $summary->addContents(new CustomCollection(['same-key' => $this->content('item_potion')]));
        $summary->addContents(new CustomCollection(['same-key' => $this->content('item_elixir')]));

        $this->assertSame(2, $summary->getTotalCount());
    }

    #[Test]
    public function 別のサマリーをマージできる(): void
    {
        $summary = new ResourceDeliverySummary;
        $summary->addContents($this->contents(['item_potion']));

        $other = new ResourceDeliverySummary;
        $other->addContents($this->contents(['item_elixir']));

        $summary->merge($other);

        $this->assertSame(2, $summary->getTotalCount());
        $this->assertSame(
            ['item_potion', 'item_elixir'],
            $summary->getContents()->map(fn ($content) => $content->getId())->values()->all()
        );
    }

    #[Test]
    public function 対象タイプを指定しなければ超過扱いにしない(): void
    {
        $summary = new ResourceDeliverySummary;
        $summary->addContents($this->contents(['item_potion'], ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED));

        $this->assertFalse($summary->hasResourceOverflow([]));
    }

    #[Test]
    public function 上限到達とインベントリ満杯を超過とみなす(): void
    {
        foreach ([
            ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED,
            ResourceDeliveryResultReason::INVENTORY_FULL,
        ] as $reason) {
            $summary = new ResourceDeliverySummary;
            $summary->addContents($this->contents(['item_potion'], $reason));

            $this->assertTrue($summary->hasResourceOverflow(['item']), "{$reason->value} が超過扱いにならない");
        }
    }

    #[Test]
    public function 他の失敗理由は超過扱いにしない(): void
    {
        // メールボックスへ回しただけなら配送は成立している
        $summary = new ResourceDeliverySummary;
        $summary->addContents($this->contents(['item_potion'], ResourceDeliveryResultReason::SEND_TO_MAILBOX));

        $this->assertFalse($summary->hasResourceOverflow(['item']));
    }

    #[Test]
    public function 対象外のタイプが超過していても無視する(): void
    {
        $summary = new ResourceDeliverySummary;
        $summary->addContents($this->contents(['item_potion'], ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED));

        $this->assertFalse($summary->hasResourceOverflow(['diamond']));
    }

    #[Test]
    public function 対象タイプが1件でも超過していれば超過(): void
    {
        $summary = new ResourceDeliverySummary;
        $summary->addContents($this->contents(['item_potion']));
        $summary->addContents($this->contents(['item_elixir'], ResourceDeliveryResultReason::RESOURCE_LIMIT_REACHED));

        $this->assertTrue($summary->hasResourceOverflow(['item']));
    }

    #[Test]
    public function 失敗が無ければ超過ではない(): void
    {
        $summary = new ResourceDeliverySummary;
        $summary->addContents($this->contents(['item_potion', 'item_elixir']));

        $this->assertFalse($summary->hasResourceOverflow(['item']));
    }

    /**
     * @param  list<string>  $ids
     * @return CustomCollection<array-key, ResourceDeliveryContent>
     */
    private function contents(array $ids, ?ResourceDeliveryResultReason $failureReason = null): CustomCollection
    {
        return new CustomCollection(
            array_map(fn (string $id) => $this->content($id, $failureReason), $ids)
        );
    }

    private function content(string $id, ?ResourceDeliveryResultReason $failureReason = null): ResourceDeliveryContent
    {
        $content = ResourceDeliveryContent::fromResource(Resource::item($id, 1));

        if ($failureReason !== null) {
            $content->setFailureReason($failureReason);
        }

        return $content;
    }
}
