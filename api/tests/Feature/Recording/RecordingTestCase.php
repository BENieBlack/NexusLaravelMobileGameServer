<?php

namespace Tests\Feature\Recording;

use Illuminate\Support\Facades\DB;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * 動画撮影用シーダーのテストの共通の土台。
 *
 * テスト全体をトランザクションで包み、終わると巻き戻す（既定の動作）。
 * テスト用 DB にある既存の行を壊さないため、件数の検査は「マーカー付きの行」か
 * 「前後の差」で行う。
 */
abstract class RecordingTestCase extends TestCase
{
    use RefreshMultipleDatabases;

    /**
     * シーダーを流す。db:seed 経由なので command が付く（出力は捨てる）。
     *
     * @param  class-string  $seeder
     */
    protected function runSeeder(string $seeder): void
    {
        $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])->assertExitCode(0);
    }

    protected function countAgreements(bool $markedOnly): int
    {
        $query = DB::connection('sys')->table('sys_agreement');

        return $markedOnly ? $query->where('version', 'like', 'rec-%')->count() : $query->count();
    }

    protected function countMaintenance(bool $markedOnly): int
    {
        $query = DB::connection('sys')->table('sys_maintenance');

        return $markedOnly ? $query->where('title', 'like', '[REC]%')->count() : $query->count();
    }
}
