<?php

namespace Database\Seeders\Recording;

use Database\Seeders\Recording\Scenarios\RecordingAgreementSeeder;
use Database\Seeders\Recording\Scenarios\RecordingDeploySeeder;
use Database\Seeders\Recording\Scenarios\RecordingLoginBonusSeeder;

/**
 * RecordingSeeder
 *
 * 動画撮影用シードの入口。`make seed-recording` が呼ぶ。
 * 既定のシナリオ（defaultScenarios() の一覧）を順に流す。
 *
 * 拡充のしかた: シナリオのクラスを Scenarios/ に作り、既定で流したいものだけを
 * defaultScenarios() に足す。API を止める・画面を変える種類のシナリオ（メンテナンス、
 * ログインボーナス無効、リセットなど）は一覧に入れず、
 * `make seed-recording-one SEEDER=<クラス名>` で個別に流す。
 *
 * 前提: `make seed`（基本のシード）が済んでいること。
 */
class RecordingSeeder extends RecordingScenarioSeeder
{
    /**
     * 既定で流すシナリオ。上から順に実行する。
     *
     * @return list<class-string<RecordingScenarioSeeder>>
     */
    public static function defaultScenarios(): array
    {
        return [
            // デプロイが空でも version API を通すため、規約・ログインボーナスより先に流す
            RecordingDeploySeeder::class,
            RecordingAgreementSeeder::class,
            RecordingLoginBonusSeeder::class,
        ];
    }

    protected function apply(): void
    {
        $this->call(self::defaultScenarios());
    }
}
