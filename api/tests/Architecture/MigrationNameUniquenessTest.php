<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: マイグレーション名の一意性
 *
 * Laravelはマイグレーションをファイル名で識別し、実行済みかどうかを
 * migrationsテーブルに記録する。同じDBへ流すマイグレーションに同名のファイルがあると、
 * 後から流した方は「実行済み」とみなされて黙って飛ばされる。
 *
 * trx と log は同じシャードDBに同居しているため、この2グループはまとめて一意でなければならない。
 * 以前は trx/log に同名の create_*_tables.php があり、log のテーブルが作られていなかった。
 */
class MigrationNameUniquenessTest extends TestCase
{
    /**
     * 同じ物理DBへ流すマイグレーショングループ
     *
     * @var array<string, list<string>>
     */
    private const DATABASE_GROUPS = [
        'sys' => ['sys'],
        'mst' => ['mst'],
        'shard' => ['trx', 'log'],
    ];

    #[Test]
    public function test_migration_names_are_unique_per_database(): void
    {
        $violations = [];

        foreach (self::DATABASE_GROUPS as $database => $groups) {
            $pathsByName = [];

            foreach ($groups as $group) {
                foreach ($this->migrationFiles($group) as $file) {
                    $pathsByName[basename($file)][] = $this->relativePath($file);
                }
            }

            foreach ($pathsByName as $name => $paths) {
                if (count($paths) > 1) {
                    sort($paths);
                    $violations[] = "[{$database}] {$name}:\n  ".implode("\n  ", $paths);
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "同じDBへ流すマイグレーションに同名のファイルがあります。後から流した方が飛ばされます:\n"
            .implode("\n", $violations)."\n"
            .'log 側は *_log_tables.php のように名前を分けてください。'
        );
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(string $group): array
    {
        // Dockerでは api/ が html/ にマウントされるため、api と packages は別々に辿る
        $files = array_merge(
            glob(dirname(__DIR__, 2)."/database/migrations/{$group}/*.php") ?: [],
            glob(dirname(__DIR__, 3)."/packages/*/database/migrations/{$group}/*.php") ?: [],
        );

        sort($files);

        return $files;
    }

    private function relativePath(string $path): string
    {
        $base = realpath(dirname(__DIR__, 3));
        $real = realpath($path) ?: $path;

        return $base === false ? $real : str_replace($base.'/', '', $real);
    }
}
