<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * アーキテクチャテスト: mst テーブルは string の id カラムを必ず持つ
 *
 * 参照の形（mst_xxx_id）を揃え、運営のシートの行を id で識別し、
 * Repository の queryOrMemory が id を鍵にできるようにするため。
 * 複合の自然キーを持つ表（*_content など）も id を持ち、自然キーは unique（uk_xxx）で守る。
 * 除外は __l10n テーブル（主キーは親の ID + language）と Laravel 標準テーブルだけ（.claude/database.md「ID と主キー」）。
 *
 * 既存の違反は作り直しがモデル・シーダー・シート・クライアントへ波及するため、解消待ちとして一覧に残して通す。
 * 一覧のテーブルが直ったら、一覧から消さないとこのテストが失敗する（一覧が腐らないようにするため）。
 * 新しい mst テーブルは最初から id を持たせ、一覧へ足さない。
 */
class MstRequiresStringIdTest extends TestCase
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
            'mst_in_app_purchase_content',
            'mst_in_app_purchase_effect',
        ],
        'packages/nexus-core/database/migrations/mst/2026_01_01_000001_create_resource_tables.php' => [
            'mst_unit_level',
            'mst_equipment_level',
        ],
        'packages/nexus-event/database/migrations/mst/2026_01_01_000001_create_event_tables.php' => [
            'mst_event_content',
        ],
        'packages/nexus-level/database/migrations/mst/2026_01_01_000001_create_level_tables.php' => [
            'mst_player_level',
        ],
        'packages/nexus-login/database/migrations/mst/2026_01_01_000001_create_login_tables.php' => [
            'mst_login_bonus_content',
        ],
        'packages/nexus-mailbox/database/migrations/mst/2026_01_01_000001_create_mailbox_tables.php' => [
            'mst_mailbox_content',
        ],
        'packages/nexus-mission/database/migrations/mst/2026_01_01_000001_create_mission_tables.php' => [
            'mst_mission_content',
        ],
        'packages/nexus-reward-track/database/migrations/mst/2026_01_01_000001_create_reward_track_tables.php' => [
            'mst_reward_track_content',
        ],
        'packages/nexus-tag/database/migrations/mst/2026_01_01_000001_create_tag_tables.php' => [
            'mst_tag_content',
        ],
        'packages/nexus-trade/database/migrations/mst/2026_01_01_000001_create_trade_tables.php' => [
            'mst_trade_product_content',
        ],
        'packages/nexus-vip/database/migrations/mst/2026_01_01_000001_create_vip_tables.php' => [
            'mst_vip_level_reward',
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
     * string の id 列を作る呼び出し（$table->string('id'...)）
     */
    private const STRING_ID_PATTERN = '/\$table->string\(\s*[\'"]id[\'"]/';

    #[Test]
    public function test_mst_tables_have_string_id_except_pending(): void
    {
        $found = [];

        foreach ($this->migrationFiles() as $file) {
            $contents = file_get_contents($file);
            $this->assertNotFalse($contents, "読み込めません: {$file}");

            foreach ($this->findTablesWithoutStringId($contents) as $table) {
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
            "mst テーブルは string の id カラムが必須です（\$table->string('id')->primary()。.claude/database.md「ID と主キー」）:\n"
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
    public function test_detector_finds_tables_without_string_id(): void
    {
        $sample = <<<'PHP'
        Schema::create('mst_good', function (Blueprint $table) {
            $table->string('id')->primary()->comment('ID');
        });
        Schema::create('mst_no_id', function (Blueprint $table) {
            $table->string('mst_unit_id')->comment('参照');
            $table->primary(['mst_unit_id', 'level']);
        });
        Schema::create('mst_auto_id', function (Blueprint $table) {
            $table->id()->comment('ID');
        });
        Schema::create('mst_good_content', function (Blueprint $table) {
            $table->string('id', 191)->primary()->comment('ID');
            $table->string('content_type')->comment('種別');
        });
        Schema::create('mst_unit__l10n', function (Blueprint $table) {
            $table->string('mst_unit_id')->comment('参照');
            $table->primary(['mst_unit_id', 'language']);
        });
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
        });
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
        });
        PHP;

        $this->assertSame(
            ['mst_no_id', 'mst_auto_id'],
            $this->findTablesWithoutStringId($sample)
        );
    }

    /**
     * migration の中身から、string の id 列を持たない mst テーブル名を返す
     *
     * Schema::create ごとに文字列を区切り、区切った範囲の中で string('id') を探す。
     * __l10n テーブルと Laravel 標準テーブルは除外する。
     *
     * @return list<string>
     */
    private function findTablesWithoutStringId(string $contents): array
    {
        // 先頭の要素は最初の Schema::create より前なので捨てる
        $parts = preg_split("/Schema::create\(\s*'([^']+)'/", $contents, -1, PREG_SPLIT_DELIM_CAPTURE);
        $tables = [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $name = $parts[$i];
            $body = $parts[$i + 1] ?? '';

            if (in_array($name, self::LARAVEL_STANDARD_TABLES, true) || str_ends_with($name, '__l10n')) {
                continue;
            }

            if (preg_match(self::STRING_ID_PATTERN, $body) !== 1) {
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
