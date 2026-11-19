<?php

namespace Database\Seeders\Recording\Scenarios;

use Database\Seeders\Recording\RecordingScenarioSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\Mst\_BaseMst;
use Nexus\Core\Utilities\ClockUtility;
use NexusVip\Services\VipLevelService;

/**
 * RecordingLoginBonusProgressSeeder
 *
 * 目的: ログインボーナスのカレンダー表示（login-bonus-calendar）の動画撮影のために、
 * 「特定の 1 人のプレイヤー」の受け取り状況を、シナリオ（下の表）の状態にそろえる。
 * 設計: docs/design/login-bonus-calendar/db.md 6 節。
 *
 * ## 他のシナリオとの違い（注意）
 * 他の動画用シナリオはマスター・規約などの「マーカー付きの行」だけを作る・消す。
 * このシナリオは**既存プレイヤーの trx（trx_login_bonus・trx_vip_login_bonus）と
 * sys_player（last_login_at・vip_point）を書き換える**。trx にはマーカー列が無いので、
 * 対象のプレイヤーを環境変数で明示した場合だけ動く（誤って他のプレイヤーを書き換えないため）。
 * 既定のシナリオ（make seed-recording）には入れない。
 *
 * ## 使い方（クライアントの --e2e-reset は毎回新しいプレイヤーを作る）
 * 1. クライアントを 1 回起動して（sign_up 後でよい）、画面などから my_id を控える
 * 2. その my_id と、シナリオの番号を渡して流す:
 *      make seed-recording-one SEEDER=RecordingLoginBonusProgressSeeder \
 *        RECORDING_PLAYER_MY_ID=<my_id> RECORDING_LOGIN_BONUS_SCENARIO=2
 * 3. クライアントで login（ホームへ進む）してカレンダーを撮る
 * 4. 別の状態を撮るときは、番号だけ替えて流し直す（何度流しても同じ結果。上書き）。
 *    新しいプレイヤーで撮り直すときは 1 からやり直す
 * 戻し方: trx に目印が無いので RecordingResetSeeder では戻らない。別のシナリオで上書きするか、
 * プレイヤーを作り直す。mst の変更（シナリオ 6・F2）は RecordingLoginBonusSeeder（再有効化）
 * または RecordingResetSeeder で戻る。
 *
 * ## シナリオ（RECORDING_LOGIN_BONUS_SCENARIO）
 * 「login で配る前」の状態（1・2・4・5・6・F2）は last_login_at を前日にし、login で今日の分が配られる。
 * 「今日すでに配られた後」の状態（3・7・8・G）は last_login_at を今日にし、login は配らない。
 *
 * | 番号 | 状態 | login / 画面の期待 |
 * | --- | --- | --- |
 * | 1 | 初回（行なし・last_login_at = null） | 通常・VIP とも 1 日目が配られ、is_granted_now が両段で真 |
 * | 2 | 3 日目まで受け取り済みの翌日（前日に 3 日目） | 4 日目が配られる |
 * | 3 | 同じ日の 2 回目（4 日目を今日配布済み・last_login_at = 今日） | login_bonus_list は空。is_granted_today 真・is_granted_now 偽 |
 * | 4 | 7 日目を配る（前日に 6 日目） | 7 日目が配られる |
 * | 5 | 1 周後の 1 日目（前日に 7 日目） | 日数が 1 に戻る |
 * | 6 | VIP の段なし（今のレベルの VIP マスターを無効化。通常は 1 と同じ初回） | vip_login_bonus_calendar が null |
 * | 7 | VIP だけ今日の段でない（通常は今日配布済み・VIP は前日・last_login_at = 今日） | VIP の is_granted_today が偽 |
 * | 8 | VIP の再受け取り（今日配布済み・受取時のレベルより今のレベルが高い・last_login_at = 今日） | can_re_receive が真。POST /login_bonus/receive で配られる。**last_login_at を今日にするのは、login で新しい日の分が配られて再受け取りの前提（今日の段）が崩れないようにするため** |
 * | F2 | 通常のマスターが無効で VIP だけある（行なし・初回・daily_login を無効化） | login_bonus_calendar が null・VIP の段は出る |
 * | G | 通常・VIP の両段が今日の段でない（両方とも前日に 3 日目・last_login_at = 今日） | 両段とも is_granted_today が偽 |
 *
 * 冪等性: 同じ my_id・同じ番号なら何度流しても同じ結果（trx は主キーで上書き、マスターは更新のみ）。
 * 環境ガード: local / testing 以外では動かない（基底クラス）。RECORDING_PLAYER_MY_ID が無い・該当プレイヤーが
 * いない・番号が不明のときは、何も書かずに警告を出して終わる。
 * 前提: make seed 済み（VIP のマスター・VIP レベルのマスター）。
 */
class RecordingLoginBonusProgressSeeder extends RecordingScenarioSeeder
{
    /** 対象のプレイヤー（sys_player.my_id）を指す環境変数 */
    public const ENV_PLAYER_MY_ID = 'RECORDING_PLAYER_MY_ID';

    /** シナリオの番号を指す環境変数 */
    public const ENV_SCENARIO = 'RECORDING_LOGIN_BONUS_SCENARIO';

    /** 指定できるシナリオの番号 */
    public const SCENARIOS = ['1', '2', '3', '4', '5', '6', '7', '8', 'F2', 'G'];

    protected function apply(): void
    {
        $myId = $this->env(self::ENV_PLAYER_MY_ID);
        $scenario = strtoupper($this->env(self::ENV_SCENARIO) ?? '');

        if ($myId === null) {
            $this->warn(self::ENV_PLAYER_MY_ID.' が無いので何もしません（対象のプレイヤーの my_id を指定してください）。');

            return;
        }
        if (! in_array($scenario, self::SCENARIOS, true)) {
            $this->warn(self::ENV_SCENARIO.' は '.implode(' / ', self::SCENARIOS).' のどれかを指定してください。何もしません。');

            return;
        }

        $player = DB::connection('sys')->table('sys_player')->where('my_id', $myId)->first();
        if ($player === null) {
            $this->warn("my_id = {$myId} のプレイヤーがいないので何もしません。");

            return;
        }

        $this->applyScenario((int) $player->id, $scenario);
        $this->report("プレイヤー {$myId} をシナリオ {$scenario} の状態にしました。");
    }

    private function applyScenario(int $playerId, string $scenario): void
    {
        // 日付は「今のゲーム日の開始」と「その前日の開始」。配布は受取日にこの値を書く
        $today = ClockUtility::calcGameDayStart(ClockUtility::nowToString());
        $todayStart = $today->format('Y-m-d H:i:s');
        $yesterdayStart = $today->subDay()->format('Y-m-d H:i:s');
        $now = ClockUtility::nowToString();

        // 通常のログインボーナスと、今のレベルの VIP ボーナスを使える状態に戻す（F2・6 だけ後で無効にする）
        $this->ensureDailyBonusActive();

        if ($scenario === '8') {
            // 再受け取りには「今のレベルが受取時のレベルより高い」が要る。マスターのある最大レベルまで上げる
            $this->raiseVipPoint($playerId);
        }

        $vipLevel = $this->currentVipLevel($playerId);
        $vipBonusId = $this->vipBonusIdFor($vipLevel);
        $this->setVipBonusActive($vipBonusId, true);

        $state = match ($scenario) {
            // [通常の日数, 通常の受取日, VIP の日数, VIP の受取日, VIP の受取時レベル, last_login_at]（日数 null = 行なし）
            '1', '6', 'F2' => [null, null, null, null, $vipLevel, null],
            '2' => [3, $yesterdayStart, 3, $yesterdayStart, $vipLevel, $yesterdayStart],
            '3' => [4, $todayStart, 4, $todayStart, $vipLevel, $now],
            '4' => [6, $yesterdayStart, 6, $yesterdayStart, $vipLevel, $yesterdayStart],
            '5' => [7, $yesterdayStart, 7, $yesterdayStart, $vipLevel, $yesterdayStart],
            '7' => [3, $todayStart, 3, $yesterdayStart, $vipLevel, $now],
            // 受取時のレベルを今のレベルより 1 低くする（今のレベルが 0 なら 0 のまま。再受け取りは偽になる）
            '8' => [3, $todayStart, 3, $todayStart, max(0, $vipLevel - 1), $now],
            'G' => [3, $yesterdayStart, 3, $yesterdayStart, $vipLevel, $now],
        };
        [$normalDay, $normalReceivedAt, $vipDay, $vipReceivedAt, $receivedVipLevel, $lastLoginAt] = $state;

        $connection = $this->trxConnection($playerId);

        // 通常: trx_login_bonus（type = daily の 1 行）。行なしの状態は行を消す
        $trxLoginBonus = DB::connection($connection)->table('trx_login_bonus')
            ->where('sys_player_id', $playerId)->where('type', 'daily');
        if ($normalDay === null) {
            $trxLoginBonus->delete();
        } else {
            DB::connection($connection)->table('trx_login_bonus')->updateOrInsert(
                ['sys_player_id' => $playerId, 'type' => 'daily'],
                [
                    'mst_login_bonus_id' => self::DAILY_LOGIN_BONUS_ID,
                    'day' => $normalDay,
                    'absent_days' => null,
                    'received_date' => $normalReceivedAt,
                    'updated_at' => $now,
                ],
            );
        }

        // VIP: trx_vip_login_bonus（プレイヤーごとに 1 行）
        $trxVipLoginBonus = DB::connection($connection)->table('trx_vip_login_bonus')->where('sys_player_id', $playerId);
        if ($vipDay === null) {
            $trxVipLoginBonus->delete();
        } else {
            DB::connection($connection)->table('trx_vip_login_bonus')->updateOrInsert(
                ['sys_player_id' => $playerId],
                [
                    'mst_vip_login_bonus_id' => $vipBonusId ?? 'recording_none',
                    'day' => $vipDay,
                    'vip_level' => $receivedVipLevel,
                    'received_at' => $vipReceivedAt,
                    'updated_at' => $now,
                ],
            );
        }

        DB::connection('sys')->table('sys_player')->where('id', $playerId)->update(['last_login_at' => $lastLoginAt]);

        // マスターを無効にするシナリオ（最後に行う）
        if ($scenario === '6') {
            $this->setVipBonusActive($vipBonusId, false);
        }
        if ($scenario === 'F2') {
            $this->setDailyBonusActive(false);
        }
    }

    /** 環境変数を読む。未設定・空文字は null */
    private function env(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || trim($value) === '' ? null : trim($value);
    }

    private function warn(string $message): void
    {
        $this->command?->warn('[REC] '.$message);
    }

    /** プレイヤーの割り当て先の trx シャードの接続名 */
    private function trxConnection(int $playerId): string
    {
        $nodeNo = DB::connection('sys')->table('sys_sharding_node_player as p')
            ->join('sys_sharding_node as n', 'n.id', '=', 'p.sys_sharding_node_id')
            ->where('p.sys_player_id', $playerId)
            ->value('n.node_no');

        if ($nodeNo === null) {
            throw new \RuntimeException("プレイヤー {$playerId} のシャード割り当てがありません（sign_up 済みのプレイヤーを指定してください）");
        }

        return 'trx'.$nodeNo;
    }

    private function currentVipLevel(int $playerId): int
    {
        $vipPoint = (int) DB::connection('sys')->table('sys_player')->where('id', $playerId)->value('vip_point');

        return app(VipLevelService::class)->calculateLevel($vipPoint);
    }

    /** 今のレベルの VIP ボーナス ID（配布・カレンダーと同じく id が小さい 1 件） */
    private function vipBonusIdFor(int $vipLevel): ?string
    {
        $id = DB::connection('mst')->table('mst_vip_login_bonus')
            ->where('vip_level', $vipLevel)->orderBy('id')->value('id');

        return $id === null ? null : (string) $id;
    }

    /** VIP ポイントを、VIP ボーナスのある最大レベルの必要ポイントにする（再受け取り用） */
    private function raiseVipPoint(int $playerId): void
    {
        $levels = DB::connection('mst')->table('mst_vip_login_bonus')->pluck('vip_level')->all();
        $requiredPoint = DB::connection('mst')->table('mst_vip_level')
            ->whereIn('level', $levels === [] ? [0] : $levels)
            ->orderByDesc('level')->value('required_point');

        DB::connection('sys')->table('sys_player')->where('id', $playerId)
            ->update(['vip_point' => (int) $requiredPoint]);
    }

    private function ensureDailyBonusActive(): void
    {
        $this->setDailyBonusActive(true);
    }

    private function setDailyBonusActive(bool $isActive): void
    {
        _BaseMst::allowWrites(function () use ($isActive): void {
            DB::connection('mst')->table('mst_login_bonus')
                ->where('id', self::DAILY_LOGIN_BONUS_ID)
                ->update(['is_active' => $isActive, 'updated_at' => ClockUtility::nowToString()]);
        });
    }

    private function setVipBonusActive(?string $vipBonusId, bool $isActive): void
    {
        if ($vipBonusId === null) {
            return;
        }

        _BaseMst::allowWrites(function () use ($vipBonusId, $isActive): void {
            DB::connection('mst')->table('mst_vip_login_bonus')
                ->where('id', $vipBonusId)
                ->update(['is_active' => $isActive, 'updated_at' => ClockUtility::nowToString()]);
        });
    }
}
