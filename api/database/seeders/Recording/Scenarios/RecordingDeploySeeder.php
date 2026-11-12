<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingDeploySeeder
 *
 * 目的: sys_deploy が空でも version API（GET /api/auth/version）が成功するよう、
 * 最小のデプロイ情報を用意する。version API は「有効かつ start_at を過ぎた sys_deploy」が
 * 1件も無いと SystemDataException（299）になるため、タイトル画面の最初で止まってしまう。
 * 必要なのは sys_deploy / sys_deploy_master / sys_deploy_asset の各1行。
 * sys_deploy_master_table（配信ファイルの一覧）は作らない。master.tables は空になり、
 * クライアントは同梱のマスターで進む（配信ファイルの取得を撮る場合は運営ツールでデプロイする）。
 *
 * 使うケース（client_design.md 7節）: 1〜15 すべての前提（version API を通す）。
 * ケース 14 は未取得の版が要るが、このデプロイは配信ファイルを持たないので代用にならない。
 *
 * 冪等性: deploy_key 固定の updateOrInsert。何度流しても各1行。
 * 副作用: 有効なデプロイが既にあれば何もしない（本物のデプロイを上書きしない）。
 * 追加する行は description が `[REC]` で始まり、RecordingResetSeeder が消す。
 * 自分が追加した行が有効なうちは「有効な行がある」とみなすが、更新するだけで増えない。
 * キャッシュ: 最新デプロイは Redis に最大1時間キャッシュされるため、追加後に消して即時に反映する。
 */
class RecordingDeploySeeder extends RecordingScenarioSeeder
{
    /** 既存の deploy_key（YYYYMMDDN 形式の 2026xxxxx）と衝突しない、過去の固定値 */
    public const DEPLOY_KEY = 190001010;

    protected function apply(): void
    {
        $now = ClockUtility::nowToString();

        if ($this->hasOtherActiveDeploy($now)) {
            $this->report('有効なデプロイが既にあるため、デプロイは追加しません。');

            return;
        }

        $description = self::DEPLOY_DESCRIPTION_PREFIX.' 動画撮影用の最小デプロイ';
        $connection = DB::connection('sys');

        $connection->table('sys_deploy_master')->updateOrInsert(
            ['deploy_key' => self::DEPLOY_KEY],
            [
                'hash' => hash('sha256', 'recording_master'),
                'deploy_date' => '1900-01-01',
                'deploy_count' => 0,
                'status' => 'completed',
                'deployed_by' => 'recording',
                'deployed_at' => '1900-01-01 00:00:00',
                'description' => $description,
                'updated_at' => $now,
            ],
        );
        $connection->table('sys_deploy_asset')->updateOrInsert(
            ['deploy_key' => self::DEPLOY_KEY],
            [
                'hash' => hash('sha256', 'recording_asset'),
                'deploy_date' => '1900-01-01',
                'deploy_count' => 0,
                'status' => 'completed',
                'deployed_by' => 'recording',
                'deployed_at' => '1900-01-01 00:00:00',
                'description' => $description,
                'updated_at' => $now,
            ],
        );

        $masterId = $connection->table('sys_deploy_master')->where('deploy_key', self::DEPLOY_KEY)->value('id');
        $assetId = $connection->table('sys_deploy_asset')->where('deploy_key', self::DEPLOY_KEY)->value('id');

        $connection->table('sys_deploy')->updateOrInsert(
            ['deploy_key' => self::DEPLOY_KEY],
            [
                'start_at' => '1900-01-01 00:00:00',
                'sys_deploy_master_id' => $masterId,
                'sys_deploy_asset_id' => $assetId,
                'is_active' => true,
                'updated_at' => $now,
            ],
        );

        $this->clearApiCaches();

        $this->report('最小のデプロイを用意しました（deploy_key='.self::DEPLOY_KEY.'）。');
    }

    /**
     * 動画用以外の、有効かつ開始済みのデプロイがあるか。
     * あれば version API はそれで成功するので、何も足さない。
     */
    private function hasOtherActiveDeploy(string $now): bool
    {
        return DB::connection('sys')->table('sys_deploy')
            ->where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where('deploy_key', '!=', self::DEPLOY_KEY)
            ->exists();
    }
}
