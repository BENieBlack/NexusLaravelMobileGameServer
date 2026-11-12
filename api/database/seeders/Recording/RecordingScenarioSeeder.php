<?php

namespace Database\Seeders\Recording;

use Database\Seeders\Recording\Scenarios\RecordingDeploySeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use NexusMaintenance\Services\MaintenanceService;

/**
 * RecordingScenarioSeeder
 *
 * クライアントの動作確認を動画に撮るためのシナリオ用シーダーの基底クラス。
 * 使い方は README.md（このフォルダ）を参照。
 *
 * 守らせること:
 * - 環境ガード: local / testing 以外では動かさない（本番へ誤って流さないため）
 * - 追加専用: 既存シーダー（truncate で作り直す方式）と違い、既存データを消さない。
 *   消してよいのは、下のマーカーが付いた「動画用の行」だけ
 * - 冪等: 何度流しても同じ結果になる（updateOrInsert など、キーで上書きする）
 *
 * 流れ: run() が環境ガードを通した後、サブクラスの apply() を呼ぶ。
 * サブクラスは run() を上書きせず、apply() だけを書く。
 */
abstract class RecordingScenarioSeeder extends Seeder
{
    /** 動画用の規約（sys_agreement）は version がこの接頭辞で始まる */
    public const AGREEMENT_VERSION_PREFIX = 'rec-';

    /** 動画用のメンテナンス（sys_maintenance）は title がこの接頭辞で始まる */
    public const MAINTENANCE_TITLE_PREFIX = '[REC]';

    /** 動画用のデプロイ（sys_deploy_master / sys_deploy_asset）は description がこの接頭辞で始まる */
    public const DEPLOY_DESCRIPTION_PREFIX = '[REC]';

    /** 動画用に有効・無効を切り替える通常ログインボーナスの ID（mst_login_bonus.id） */
    public const DAILY_LOGIN_BONUS_ID = 'daily_login';

    /** このシーダーを流してよい環境 */
    private const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    /**
     * 環境ガードを通してから、シナリオを適用する。
     *
     * @throws \RuntimeException 許可された環境でないとき
     */
    final public function run(): void
    {
        $this->assertSafeEnvironment();

        $this->apply();
    }

    /**
     * シナリオの中身。サブクラスで実装する。
     * 追加専用・冪等・マーカー付きの行だけを触る、を守ること。
     */
    abstract protected function apply(): void;

    /**
     * 許可された環境（local / testing）でなければ例外で止める。
     *
     * 動画用のデータは本番に入ってはならず、メンテナンスの有効化は API を止めるため、
     * 設定ミスで流れても被害が出ないよう、各シナリオの入口で必ず検査する。
     *
     * @throws \RuntimeException
     */
    protected function assertSafeEnvironment(): void
    {
        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            throw new \RuntimeException(sprintf(
                '動画撮影用シーダーは %s 環境でのみ実行できます（現在: %s）',
                implode(' / ', self::ALLOWED_ENVIRONMENTS),
                app()->environment(),
            ));
        }
    }

    /**
     * メンテナンスとデプロイのキャッシュを消し、DB の変更を API に即時に反映させる。
     *
     * 503 を返すミドルウェアは MaintenanceService のキャッシュ（既定 60 秒）を、
     * version API は Redis のキャッシュ（3600 秒）を先に見るため、DB を変えただけでは
     * 撮影のタイミングに間に合わない。メンテナンス・デプロイを変えるシナリオが末尾で呼ぶ。
     * version 側のキーは Repository の buildCacheKey（`sys:{テーブル名}:{キー}`）と同じ形で、
     * Repository の clearCache は protected でシーダーから呼べないため、キーを直接消す。
     */
    protected function clearApiCaches(): void
    {
        app(MaintenanceService::class)->clearCache();

        $redis = Cache::store('redis');
        $redis->forget('sys:sys_maintenance:current_maintenance');
        $redis->forget('sys:sys_deploy:latest_downloadable');
        $redis->forget('sys:sys_deploy:deploy_key:'.RecordingDeploySeeder::DEPLOY_KEY);
    }

    /**
     * 実行結果を1行出す。db:seed 以外（テストなど）で command が無いときは何も出さない。
     */
    protected function report(string $message): void
    {
        $this->command?->info('[REC] '.$message);
    }
}
