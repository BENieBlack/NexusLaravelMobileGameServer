<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ミッションのトランザクションテーブル群
 *
 * trx_progress - 出来事 × 対象（タグ）ごとの通算カウンタ。ミッションに依存しない
 * trx_mission  - ミッションの報酬を受け取った記録
 *
 * 注意: このマイグレーションは `php artisan trx:migrate` で全シャードに流す。
 * 接続はここで指定せず、実行側が --database で切り替える。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------
        // trx_progress: 出来事 × 対象ごとの通算カウンタ
        //
        // 出来事が起きるたびに、対象が持つタグごとの行と、タグを問わない行（sub_target_id = all）を
        // それぞれ進める。どのミッションがあるかに関係なく数えるので、
        // 後から足したミッションも過去の実績で達成できる。
        // ------------------------------------------------
        Schema::create('trx_progress', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('trigger_type')->comment('数えている出来事 (unit_level_up / unit_max_limit_break など。mst_mission.trigger_type と同じ値)');
            $table->string('sub_target_id')->comment('数える対象。mst_tag.id か、タグを問わない通算の all');
            $table->unsignedInteger('progress_value')->default(0)->comment('通算の回数');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'trigger_type', 'sub_target_id'], 'pk_progress');
        });

        // ------------------------------------------------
        // trx_mission: ミッションの報酬を受け取った記録
        //
        // 達成したかどうかは trx_progress と mst_mission.target_value から求めるので持たない。
        // 受け取ったときに1行作る（行が無ければ未受け取り）。
        // ------------------------------------------------
        Schema::create('trx_mission', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_mission_id')->comment('mst_missionテーブルのID');
            $table->dateTime('received_at')->comment('報酬を受け取った日時');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // 1プレイヤー × 1ミッションで1行（二重受け取りを防ぐ）
            $table->primary(['sys_player_id', 'mst_mission_id'], 'pk_mission');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_mission');
        Schema::dropIfExists('trx_progress');
    }
};
