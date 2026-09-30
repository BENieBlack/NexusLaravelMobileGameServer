<?php

namespace Tests\Unit\Models\Sys;

use App\Models\Sys\SysShardingNode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysShardingNode のテスト
 *
 * プレイヤーをどのノードに割り当てられるかの判定と、
 * ノード番号から接続名を解決する部分を確認する。
 */
class SysShardingNodeTest extends TestCase
{
    #[Test]
    public function ステータスがactiveならアクティブと判定する(): void
    {
        $this->assertTrue($this->makeNode(['status' => SysShardingNode::STATUS_ACTIVE])->isActive());
        $this->assertFalse($this->makeNode(['status' => SysShardingNode::STATUS_INACTIVE])->isActive());
        $this->assertFalse($this->makeNode(['status' => SysShardingNode::STATUS_MAINTENANCE])->isActive());
    }

    #[Test]
    public function メンテナンス中を判定できる(): void
    {
        $this->assertTrue($this->makeNode(['status' => SysShardingNode::STATUS_MAINTENANCE])->isInMaintenance());
        $this->assertFalse($this->makeNode(['status' => SysShardingNode::STATUS_ACTIVE])->isInMaintenance());
    }

    #[Test]
    public function 読み書き可否はアクティブであることが前提(): void
    {
        $active = $this->makeNode(['status' => SysShardingNode::STATUS_ACTIVE]);
        $this->assertTrue($active->isWritable());
        $this->assertTrue($active->isReadable());

        // フラグが立っていてもメンテナンス中なら不可
        $maintenance = $this->makeNode(['status' => SysShardingNode::STATUS_MAINTENANCE]);
        $this->assertFalse($maintenance->isWritable());
        $this->assertFalse($maintenance->isReadable());

        // アクティブでもフラグが下りていれば不可
        $readOnly = $this->makeNode(['is_writable' => false]);
        $this->assertFalse($readOnly->isWritable());
        $this->assertTrue($readOnly->isReadable());
    }

    #[Test]
    public function 空きがあるアクティブなノードだけがプレイヤーを受け入れる(): void
    {
        $this->assertTrue($this->makeNode(['current_player_count' => 99, 'max_connections' => 100])->canAcceptPlayer());

        // 上限に達している
        $this->assertFalse($this->makeNode(['current_player_count' => 100, 'max_connections' => 100])->canAcceptPlayer());

        // 空きがあってもメンテナンス中なら受け入れない
        $this->assertFalse($this->makeNode([
            'status' => SysShardingNode::STATUS_MAINTENANCE,
            'current_player_count' => 0,
        ])->canAcceptPlayer());
    }

    #[Test]
    public function 使用率を百分率で返す(): void
    {
        $this->assertSame(25.0, $this->makeNode(['current_player_count' => 25, 'max_connections' => 100])->calcUsagePercentage());
        $this->assertSame(0.0, $this->makeNode(['current_player_count' => 0, 'max_connections' => 100])->calcUsagePercentage());
    }

    #[Test]
    public function 上限が0なら使用率は0で割り算しない(): void
    {
        $this->assertSame(0.0, $this->makeNode(['current_player_count' => 10, 'max_connections' => 0])->calcUsagePercentage());
    }

    #[Test]
    public function ノード番号から接続名を解決する(): void
    {
        $node = $this->makeNode(['node_no' => 2]);

        $this->assertSame('trx2', $node->getTrxConnectionName());
        // config/database.php に定義された接続設定が引ける
        $this->assertIsArray($node->getConnectionConfig());
    }

    #[Test]
    public function 定義のない接続名なら設定はnullになる(): void
    {
        $this->assertNull($this->makeNode(['node_no' => 999])->getConnectionConfig());
    }

    #[Test]
    public function アクセサで値を出し入れできる(): void
    {
        $node = new SysShardingNode;
        $node->setSysShardingId(3);
        $node->setNodeName('node3');
        $node->setNodeNo(3);
        $node->setWeight(50);
        $node->setStatus(SysShardingNode::STATUS_INACTIVE);
        $node->setMaxConnections(500);
        $node->setCurrentPlayerCount(10);

        $this->assertSame(3, $node->getSysShardingId());
        $this->assertSame('node3', $node->getNodeName());
        $this->assertSame(3, $node->getNodeNo());
        $this->assertSame(50, $node->getWeight());
        $this->assertSame(SysShardingNode::STATUS_INACTIVE, $node->getStatus());
        $this->assertSame(500, $node->getMaxConnections());
        $this->assertSame(10, $node->getCurrentPlayerCount());
    }

    #[Test]
    public function 読み書きの可否を切り替えられる(): void
    {
        // 縮退運転でノードを読み取り専用に落とすときに使う
        $node = $this->makeNode();

        $node->setIsWritable(false);
        $this->assertFalse($node->isWritable());
        $this->assertTrue($node->isReadable(), '書けなくても読める状態を作れる');

        $node->setIsReadable(false);
        $this->assertFalse($node->isReadable());
    }

    #[Test]
    public function 利用可能なステータスの一覧を取れる(): void
    {
        $this->assertSame(
            [
                SysShardingNode::STATUS_ACTIVE,
                SysShardingNode::STATUS_INACTIVE,
                SysShardingNode::STATUS_MAINTENANCE,
            ],
            SysShardingNode::availableStatuses()
        );
    }

    #[Test]
    public function 人数は0を下回らない(): void
    {
        // 割り当て解除を二重に走らせても負にしない
        $node = $this->makeNode(['current_player_count' => 0]);

        $this->assertFalse($node->decrementPlayerCount());
        $this->assertSame(0, $node->getCurrentPlayerCount());
    }

    #[Test]
    public function レスポンス用の配列ではidに接頭辞が付く(): void
    {
        $node = $this->makeNode();
        $node->setAttribute('id', 5);

        $array = $node->toResponseArray();

        $this->assertSame(5, $array['sys_sharding_node_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeNode(array $attributes = []): SysShardingNode
    {
        return new SysShardingNode(array_merge([
            'sys_sharding_id' => 1,
            'node_name' => 'node1',
            'node_no' => 1,
            'weight' => 100,
            'status' => SysShardingNode::STATUS_ACTIVE,
            'is_writable' => true,
            'is_readable' => true,
            'max_connections' => 100,
            'current_player_count' => 0,
        ], $attributes));
    }
}
