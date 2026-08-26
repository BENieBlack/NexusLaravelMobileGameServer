<?php

namespace Tests\Unit\Models\Mst;

use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Domain\Mailbox\Constants\SenderType;
use App\Models\Mst\MstMailbox;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MstMailbox のテスト
 *
 * category / priority / sender_type はEnumにキャストされる。
 * レスポンス用配列では値を文字列に落としつつ、ラベルやアイコンも併せて返す。
 */
class MstMailboxTest extends TestCase
{
    #[Test]
    public function enumのアクセサはキャストされた値を返す(): void
    {
        $mstMailbox = $this->makeMailbox();

        $this->assertSame(Category::SYSTEM, $mstMailbox->getCategory());
        $this->assertSame(Priority::IMPORTANT, $mstMailbox->getPriority());
        $this->assertSame(SenderType::SYSTEM, $mstMailbox->getSenderType());
    }

    #[Test]
    public function レスポンス配列ではenumを値とラベルに展開する(): void
    {
        $array = $this->makeMailbox()->toResponseArray();

        $this->assertSame(Category::SYSTEM->value, $array['category']);
        $this->assertSame(Category::SYSTEM->label(), $array['category_label']);
        $this->assertSame(Category::SYSTEM->icon(), $array['category_icon']);

        $this->assertSame(Priority::IMPORTANT->value, $array['priority']);
        $this->assertSame(Priority::IMPORTANT->label(), $array['priority_label']);
        $this->assertSame(Priority::IMPORTANT->color(), $array['priority_color']);
        $this->assertSame(Priority::IMPORTANT->icon(), $array['priority_icon']);

        $this->assertSame(SenderType::SYSTEM->value, $array['sender_type']);
        $this->assertSame(SenderType::SYSTEM->label(), $array['sender_type_label']);
    }

    #[Test]
    public function enumが入っていなければラベルは付かない(): void
    {
        $array = (new MstMailbox(['id' => 'mailbox_001']))->toResponseArray();

        $this->assertArrayNotHasKey('category_label', $array);
        $this->assertArrayNotHasKey('priority_color', $array);
        $this->assertArrayNotHasKey('sender_type_label', $array);
    }

    private function makeMailbox(): MstMailbox
    {
        return new MstMailbox([
            'id' => 'mailbox_001',
            'mst_message_id' => 'message_001',
            'category' => Category::SYSTEM->value,
            'priority' => Priority::IMPORTANT->value,
            'sender_type' => SenderType::SYSTEM->value,
            'expires_in_days' => 30,
        ]);
    }
}
