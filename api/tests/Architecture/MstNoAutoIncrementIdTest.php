<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: mst テーブルの主キーに AUTO_INCREMENT を使わない
 *
 * mst の id を自動採番にすると、環境ごと・投入順ごとに採番がズレ、
 * 他のマスタが持つ参照カラム（mst_xxx_id）との JOIN が効かなくなる。
 * mst の id は人が決める安定した文字列（string）にするか、複合主キーにする（.claude/database.md「ID と主キー」）。
 *
 * 既存の違反は作り直しがモデル・シーダー・テストへ波及するため、解消待ちとして一覧に残して通す。
 * 一覧のテーブルが直ったら、一覧から消さないとこのテストが失敗する（一覧が腐らないようにするため）。
 */
class MstNoAutoIncrementIdTest extends TestCase
{
    /**
     * 解消待ちの違反（相対パス => テーブル名の一覧）
     * 新しい違反をここへ足してはならない。直したら消す。
     *
     * @var array<string, list<string>>
     */
    private const PENDING_VIOLATIONS = [
        'packages/nexus-core-billing/database/migrations/mst/2026_01_01_000001_create_billing_tables.php' => [
            'mst_billing_platform_product',
            'mst_in_app_purchase',
        ],
        'packages/nexus-vip/database/migrations/mst/2026_01_01_000001_create_vip_tables.php' => [
            'mst_vip_login_bonus_content',
        ],
    ];

    /**
     * 検査の対象外にする Laravel 標準テーブル（キュー・キャッシュ用で、マスタではない）
     *
     * @var list<string>
     */
    private const LARAVEL_STANDARD_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * 自動採番の列を作るメソッド呼び出し（id() / *increments() / ->autoIncrement()）
     */
    private const AUTO_INCREMENT_PATTERN = '/\$table->(?:id|increments|tinyIncrements|smallIncrements|mediumIncrements|bigIncrements|integerIncrements)\s*\(|->autoIncrement\s*\(/';

    #[Test]
    public function test_mst_tables_do_not_use_auto_increment_except_pending(): void
    {
        $found = [];

        foreach ($this->migrationFiles() as $file) {
            $contents = file_get_contents($file);
            $this->assertNotFalse($contents, "読み込めません: {$file}");

            foreach ($this->findAutoIncrementTables($contents) as $table) {
                $found[$this->relativePath($file)][] = $table;
            }
        }

        $unexpected = [];
        foreach ($found as $path => $tables) {
            foreach ($tables as $table) {
                if (! in_array($table, self::PENDING_VIOLATIONS[$path] ?? [], true)) {
                    $unexpected[] = "{$path}: {$table}";
                }
            }
        }

        $this->assertSame(
            [],
            $unexpected,
            "mst テーブルの id に AUTO_INCREMENT は使えません。string の id か複合主キーにしてください（.claude/database.md「ID と主キー」）:\n"
            .implode("\n", $unexpected)
        );

        // 直ったのに一覧へ残っているものを検出する
        $stale = [];
        foreach (self::PENDING_VIOLATIONS as $path => $tables) {
            foreach ($tables as $table) {
                if (! in_array($table, $found[$path] ?? [], true)) {
                    $stale[] = "{$path}: {$table}";
                }
            }
        }

        $this->assertSame(
            [],
            $stale,
            "解消済み（または存在しない）のに PENDING_VIOLATIONS に残っています。一覧から消してください:\n"
            .implode("\n", $stale)
        );
    }

    #[Test]
    public function test_detector_finds_auto_increment_tables(): void
    {
        $sample = <<<'PHP'
        Schema::create('mst_bad_id', function (Blueprint $table) {
            $table->id()->comment('ID');
        });
        Schema::create('mst_bad_increments', function (Blueprint $table) {
            $table->bigIncrements('id');
        });
        Schema::create('mst_bad_auto', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement()->primary();
        });
        Schema::create('mst_good', function (Blueprint $table) {
            $table->string('id')->primary()->comment('ID');
            $table->unsignedBigInteger('mst_other_id')->comment('参照');
        });
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
        });
        PHP;

        $this->assertSame(
            ['mst_bad_id', 'mst_bad_increments', 'mst_bad_auto'],
            $this->findAutoIncrementTables($sample)
        );
    }

    /**
     * migration の中身から、自動採番の列を持つテーブル名を返す
     *
     * Schema::create ごとに文字列を区切り、区切った範囲の中で自動採番の呼び出しを探す。
     * Laravel 標準テーブルは除外する。
     *
     * @return list<string>
     */
    private function findAutoIncrementTables(string $contents): array
    {
        // 先頭の要素は最初の Schema::create より前なので捨てる
        $parts = preg_split("/Schema::create\(\s*'([^']+)'/", $contents, -1, PREG_SPLIT_DELIM_CAPTURE);
        $tables = [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $name = $parts[$i];
            $body = $parts[$i + 1] ?? '';

            if (in_array($name, self::LARAVEL_STANDARD_TABLES, true)) {
                continue;
            }

            if (preg_match(self::AUTO_INCREMENT_PATTERN, $body) === 1) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        // Dockerでは api/ が html/ にマウントされるため、api と packages は別々に辿る
        $files = array_merge(
            glob(dirname(__DIR__, 2).'/database/migrations/mst/*.php') ?: [],
            glob(dirname(__DIR__, 3).'/packages/*/database/migrations/mst/*.php') ?: [],
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
