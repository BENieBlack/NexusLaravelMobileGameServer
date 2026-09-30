<?php

namespace NexusPitr\Tests\Unit\Support;

use NexusPitr\Support\ShardMigrationPaths;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ShardMigrationPaths のテスト
 *
 * trx / log のマイグレーションは各パッケージが持っている。
 * 一覧を手で持つとパッケージを増やすたびに更新が要るため、
 * packages/ を走査して集める。
 */
class ShardMigrationPathsTest extends TestCase
{
    #[Test]
    public function パッケージのtrxマイグレーションを集める(): void
    {
        $paths = ShardMigrationPaths::find('trx');

        $this->assertContains('../packages/nexus-core/database/migrations/trx', $paths);
        $this->assertContains('../packages/nexus-album/database/migrations/trx', $paths);
    }

    #[Test]
    public function パッケージのlogマイグレーションを集める(): void
    {
        $paths = ShardMigrationPaths::find('log');

        $this->assertContains('../packages/nexus-pitr/database/migrations/log', $paths);
        $this->assertContains('database/migrations/log', $paths, 'アプリ自身のlogも含む');
    }

    #[Test]
    public function tidbの変換は最後に流す(): void
    {
        // 対象テーブルが揃ってからでないと変換できない。
        // ファイル名の日付順では保証できないのでパスの並びで最後に回している
        foreach (['trx', 'log'] as $type) {
            $paths = ShardMigrationPaths::find($type);
            $tidbPath = "../packages/nexus-tidb/database/migrations/{$type}";

            $this->assertContains($tidbPath, $paths);
            $this->assertSame($tidbPath, end($paths), "{$type} でnexus-tidbが最後になっていない");
        }
    }

    #[Test]
    public function 存在しない種別では何も返さない(): void
    {
        $this->assertSame([], ShardMigrationPaths::find('no_such_type'));
    }

    #[Test]
    public function 同じパスを重複して返さない(): void
    {
        $paths = ShardMigrationPaths::find('trx');

        $this->assertSame(array_values(array_unique($paths)), $paths);
    }
}
