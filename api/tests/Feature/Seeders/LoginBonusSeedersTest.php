<?php

namespace Tests\Feature\Seeders;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MstLoginBonusSeeder;
use Database\Seeders\Recording\Scenarios\RecordingLoginBonusSeeder;
use Illuminate\Support\Facades\DB;
use NexusLogin\DataTransferObjects\LoginBonusContent;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 通常ログインボーナスのシーダーのテスト。
 *
 * 配布量は content_quantity × amount（amount は倍率）。以前は両方に同じ値（100 など）を入れて
 * いたため、1 日目が「ダイヤ x10000」になっていた。配布量は content_quantity に入れ、amount は 1 にする。
 *
 * シーダーは mst_login_bonus* を truncate する（暗黙コミット）ので、トランザクションで包まず、
 * tearDown で空に戻す。
 */
class LoginBonusSeedersTest extends TestCase
{
    use RefreshMultipleDatabases;

    public function beginDatabaseTransaction(): void
    {
        // シーダーの truncate が暗黙コミットになるため、自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    #[Test]
    public function 基本のシードに通常ログインボーナスが入っている(): void
    {
        // make seed で mst_login_bonus が空にならないよう、DatabaseSeeder の Phase 1 から呼ばれている
        $source = (new ReflectionClass(DatabaseSeeder::class))->getFileName();
        $this->assertStringContainsString('MstLoginBonusSeeder::class', (string) file_get_contents((string) $source));
    }

    #[Test]
    public function 配布量はcontent_quantityに入り_倍率amountは1で_1日目は100になる(): void
    {
        // 操作
        $this->seed(MstLoginBonusSeeder::class);

        // 期待: 1 日目は content_quantity=100・amount=1、DTO の配布量は 100（積の 10000 ではない）
        $day1 = (array) DB::connection('mst')->table('mst_login_bonus_content')
            ->where('mst_login_bonus_id', 'daily_login')->where('day', 1)->first();
        $this->assertSame(100, (int) $day1['content_quantity']);
        $this->assertSame(1, (int) $day1['amount']);
        $this->assertSame(100, $this->toDto($day1)->getTotalQuantity());

        // 全日の amount が 1 で、配布量が content_quantity と一致する
        $this->assertSame(0, DB::connection('mst')->table('mst_login_bonus_content')->where('amount', '!=', 1)->count());
    }

    #[Test]
    public function 無償ダイヤはitemのfree_diamondで5日目はスタミナ回復薬になる(): void
    {
        // 設計 free-diamond-item: 無償ダイヤの種別（diamond）は廃止。6 日目の diamond / diamond の混在も
        // free_diamond に統一し、量（20000）は変えない。5 日目のスタミナ 50 は stamina_50 を 1 個渡す
        foreach ([MstLoginBonusSeeder::class, RecordingLoginBonusSeeder::class] as $seeder) {
            $this->cleanUp();
            $this->seed($seeder);

            $rows = collect($this->contentRows())->keyBy('day');

            foreach ([1 => 100, 3 => 100, 6 => 20000, 7 => 500] as $day => $quantity) {
                $this->assertSame('item', $rows[$day]['content_type'], "{$seeder} {$day}日目");
                $this->assertSame('free_diamond', $rows[$day]['content_mst_id'], "{$seeder} {$day}日目");
                $this->assertSame($quantity, (int) $rows[$day]['content_quantity'], "{$seeder} {$day}日目");
            }

            $this->assertSame('item', $rows[5]['content_type']);
            $this->assertSame('stamina_50', $rows[5]['content_mst_id']);
            $this->assertSame(1, (int) $rows[5]['content_quantity']);

            // 旧種別は残らない
            $this->assertSame(0, DB::connection('mst')->table('mst_login_bonus_content')
                ->whereIn('content_type', ['diamond', 'stamina'])->count());
        }
    }

    #[Test]
    public function mst_login_bonus_seederは2回流しても行数が変わらない(): void
    {
        $this->seed(MstLoginBonusSeeder::class);
        $bonus = $this->rowCount('mst_login_bonus');
        $content = $this->rowCount('mst_login_bonus_content');

        $this->seed(MstLoginBonusSeeder::class);

        $this->assertSame(1, $bonus);
        $this->assertSame(7, $content);
        $this->assertSame($bonus, $this->rowCount('mst_login_bonus'));
        $this->assertSame($content, $this->rowCount('mst_login_bonus_content'));
    }

    #[Test]
    public function recordingのシーダーも同じ内容で_2回流しても行数が変わらない(): void
    {
        // 前提: 通常のシーダーの内容を控える
        $this->seed(MstLoginBonusSeeder::class);
        $expected = $this->contentRows();
        $this->cleanUp();

        // 操作: Recording を 2 回流す
        $this->seed(RecordingLoginBonusSeeder::class);
        $count = $this->rowCount('mst_login_bonus_content');
        $this->seed(RecordingLoginBonusSeeder::class);

        // 期待: 行数が変わらず、報酬の中身（種別・ID・数量・倍率）が MstLoginBonusSeeder と同じ
        $this->assertSame(7, $count);
        $this->assertSame($count, $this->rowCount('mst_login_bonus_content'));
        $this->assertSame($expected, $this->contentRows());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentRows(): array
    {
        return DB::connection('mst')->table('mst_login_bonus_content')
            ->orderBy('day')
            ->get(['day', 'content_type', 'content_mst_id', 'content_quantity', 'amount'])
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toDto(array $row): LoginBonusContent
    {
        return new LoginBonusContent(
            contentType: (string) $row['content_type'],
            contentMstId: (string) $row['content_mst_id'],
            contentOption: null,
            contentQuantity: (int) $row['content_quantity'],
            amount: (int) $row['amount'],
            isPaid: (bool) $row['is_paid'],
        );
    }

    private function rowCount(string $table): int
    {
        return DB::connection('mst')->table($table)->count();
    }

    private function cleanUp(): void
    {
        DB::connection('mst')->table('mst_login_bonus_content')->delete();
        DB::connection('mst')->table('mst_login_bonus')->delete();
    }
}
