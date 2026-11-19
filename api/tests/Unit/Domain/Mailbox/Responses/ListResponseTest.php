<?php

namespace Tests\Unit\Domain\Mailbox\Responses;

use App\Domain\Mailbox\Responses\ListResponse;
use App\Models\Mst\MstMailbox;
use App\Models\Mst\MstMailboxContent;
use App\Models\Trx\TrxMailbox;
use Nexus\Core\Support\CustomCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ListResponse のテスト（DB に触れず、メモリ上で組んだ Model だけで検査する）
 *
 * 添付の種別のラベル・アイコンは、Response が ContentType（enum）から引く。
 * ContentType に無い種別は、DB の enum 制約により実データでは起きない防御的な枝だが、
 * 起きても落ちず、ラベルに種別そのもの・アイコンは空文字を返すことを固定する。
 */
class ListResponseTest extends TestCase
{
    #[Test]
    public function 未知の種別の添付はラベルが種別そのもの_アイコンは空になる(): void
    {
        $response = ListResponse::from(
            new CustomCollection([$this->makeTrxMailbox([
                new MstMailboxContent(['content_type' => 'legacy_unknown', 'content_mst_id' => 'x_001', 'amount' => 3, 'is_highlight' => false, 'sort_desc' => 0]),
                new MstMailboxContent(['content_type' => 'item', 'content_mst_id' => 'item_001', 'amount' => 5, 'rarity' => 'SR', 'is_highlight' => true, 'sort_desc' => 2]),
            ])]),
            [10 => ['title' => 'タイトル', 'body' => '本文']],
            ['system' => 1],
        );

        $array = $response->toArray();
        $item = $array['mailbox_list'][0];

        $this->assertSame('タイトル', $item['title']);
        $this->assertSame('本文', $item['body']);
        $this->assertTrue($item['has_content']);
        $this->assertSame([
            'content_type' => 'legacy_unknown',
            'content_type_label' => 'legacy_unknown',
            'content_type_icon' => '',
            'content_mst_id' => 'x_001',
            'amount' => 3,
            'rarity' => null,
            'is_highlight' => false,
            'sort_desc' => 0,
        ], $item['content_list'][0]);
        // 既知の種別は enum のラベルとアイコン
        $this->assertSame('アイテム', $item['content_list'][1]['content_type_label']);
        $this->assertSame('📦', $item['content_list'][1]['content_type_icon']);
        $this->assertSame(1, $array['total_unread']);
    }

    /**
     * @param  list<MstMailboxContent>  $contents
     */
    private function makeTrxMailbox(array $contents): TrxMailbox
    {
        $mstMailbox = new MstMailbox([
            'id' => 'mailbox_001',
            'mst_message_id' => 'message_001',
            'category' => 'system',
            'priority' => 'normal',
            'sender_type' => 'system',
        ]);
        $mstMailbox->setRelation('contentCollection', new CustomCollection($contents));

        $trxMailbox = new TrxMailbox([
            'sys_player_id' => 1,
            'mst_mailbox_id' => 'mailbox_001',
            'is_opened' => false,
            'is_received' => false,
            'is_protected' => false,
            'expires_at' => '2999-01-01 00:00:00',
            'created_at' => '2026-01-01 00:00:00',
        ]);
        $trxMailbox->setAttribute('id', 10);
        $trxMailbox->setRelation('mstMailbox', $mstMailbox);

        return $trxMailbox;
    }
}
