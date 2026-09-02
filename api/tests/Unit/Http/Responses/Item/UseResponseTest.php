<?php

namespace Tests\Unit\Http\Responses\Item;

use App\Http\Responses\Item\UseResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UseResponse のテスト
 *
 * アイテム使用APIの応答。クライアントは適用結果をここだけから読むため、
 * キー名と値の対応が入れ替わると回復量などが取り違えられる。
 *
 * IDキーはどのテーブルのIDか分かる名前で返す。
 */
class UseResponseTest extends TestCase
{
    #[Test]
    public function 使用結果を配列にできる(): void
    {
        $response = new UseResponse(
            mstItemId: 'item_potion_001',
            effect: 'HealHP',
            itemUsed: 2,
            appliedValue: 300,
        );

        $this->assertSame([
            'mst_item_id' => 'item_potion_001',
            'effect' => 'HealHP',
            'item_used' => 2,
            'applied_value' => 300,
        ], $response->toArray());
    }

    #[Test]
    public function jsonへの変換も同じ形になる(): void
    {
        $response = new UseResponse('item_potion_001', 'HealHP', 1, 100);

        $this->assertSame($response->toArray(), $response->jsonSerialize());
        $this->assertSame($response->toArray(), $response->toJsonResponse()->getData(true));
    }

    #[Test]
    public function 効果が無い使用も表現できる(): void
    {
        // 効果値0はエラーではなく「使ったが変化しなかった」を表す
        $response = new UseResponse('item_key_001', 'None', 1, 0);

        $this->assertSame(0, $response->toArray()['applied_value']);
        $this->assertSame('None', $response->toArray()['effect']);
    }
}
