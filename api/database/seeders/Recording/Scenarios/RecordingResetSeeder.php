<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Utilities\ClockUtility;

/**
 * RecordingResetSeeder
 *
 * 目的: 動画用に入れたものを元に戻す。既定の一覧には入れない。
 * 1. マーカー付きの規約（version が `rec-` で始まる）を削除する
 * 2. マーカー付きのメンテナンス（title が `[REC]` で始まる）を削除する（解除を含む）
 * 3. マーカー付きのデプロイ（description が `[REC]` で始まる）を削除する
 * 4. daily_login を有効に戻す（無効化シナリオの後始末）
 * 5. メンテナンス・デプロイのキャッシュを消す（即時に反映するため）
 *
 * 使うケース（client_design.md 7節）: 「規約なし」を撮り直す前（1・2）、撮影の終わり。
 *
 * 冪等性: 何度流しても同じ結果（対象が無ければ何もしない）。
 * 副作用: マーカーの無い行は消さない。ただし daily_login はマーカーが無いまま is_active=true に更新する（例外）。
 * それ以外のマーカーの無い行は消さない（運営・基本シードのデータは残る）。
 * 同意済みの記録（trx_agreement）は触らない。規約を消した後は孤立した記録が残るが、
 * 次に同じ version を入れ直しても ID が変わるため、新規ユーザーの撮影には影響しない。
 * 本物のデプロイが無い環境では、デプロイを消すと version API が失敗する。
 */
class RecordingResetSeeder extends RecordingScenarioSeeder
{
    protected function apply(): void
    {
        $sys = DB::connection('sys');

        $sys->table('sys_agreement')
            ->where('version', 'like', self::AGREEMENT_VERSION_PREFIX.'%')
            ->delete();

        $sys->table('sys_maintenance')
            ->where('title', 'like', self::MAINTENANCE_TITLE_PREFIX.'%')
            ->delete();

        $this->deleteMarkedDeploys();

        // 無効化していなくても害は無い（有効のままにするだけ）。行が無ければ何も起きない
        _BaseMst::allowWrites(function (): void {
            DB::connection('mst')->table('mst_login_bonus')
                ->where('id', self::DAILY_LOGIN_BONUS_ID)
                ->update(['is_active' => true, 'updated_at' => ClockUtility::nowToString()]);
        });

        $this->clearApiCaches();

        $this->report('動画用のデータを削除し、daily_login を有効に戻しました。');
    }

    /**
     * マーカー付きの master / asset と、それを指す sys_deploy を削除する。
     * sys_deploy は外部キーが無いので、先に ID を控えてから消す。
     */
    private function deleteMarkedDeploys(): void
    {
        $sys = DB::connection('sys');
        $like = self::DEPLOY_DESCRIPTION_PREFIX.'%';

        $masterIds = $sys->table('sys_deploy_master')->where('description', 'like', $like)->pluck('id')->all();
        $assetIds = $sys->table('sys_deploy_asset')->where('description', 'like', $like)->pluck('id')->all();

        $sys->table('sys_deploy')
            ->where('deploy_key', RecordingDeploySeeder::DEPLOY_KEY)
            ->where(fn ($q) => $q->whereIn('sys_deploy_master_id', $masterIds)->orWhereIn('sys_deploy_asset_id', $assetIds))
            ->delete();
        $sys->table('sys_deploy_master_table')->whereIn('sys_deploy_master_id', $masterIds)->delete();
        $sys->table('sys_deploy_master')->whereIn('id', $masterIds)->delete();
        $sys->table('sys_deploy_asset')->whereIn('id', $assetIds)->delete();
    }
}
