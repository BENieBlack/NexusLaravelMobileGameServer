<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RewardTrack トランザクションテーブル群
 *
 * trx_reward_track          - プレイヤーの進捗値
 * trx_reward_track_line     - プレイヤーの購入済みライン
 * trx_reward_track_milestone - プレイヤーの受け取り済みマイルストーン
 *
 * 注意: このマイグレーションは `php artisan trx:migrate` で実行してください。
 * TrxMigrateCommand が全TrxDBシャード（trx1, trx2, ...）に対して実行します。
 * 接続はここで指定せず、実行側が --database で切り替えます。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------
        // trx_reward_track（プレイヤーの進捗値）
        // ------------------------------------------------
        Schema::create('trx_reward_track', function (Blueprint $table) {
            $table->id()->comment('自動採番ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_reward_track_id')->comment('mst_reward_trackテーブルのID');
            $table->unsignedInteger('current_progress')->default(0)->comment('現在の進捗値（レベル・ポイント等）');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // 1プレイヤー × 1トラックで一意
            $table->unique(['sys_player_id', 'mst_reward_track_id'], 'uq_player_track');
            $table->index('sys_player_id');
            $table->index('mst_reward_track_id');
            $table->index('is_delete');
        });

        // ------------------------------------------------
        // trx_reward_track_line（プレイヤーの購入済みライン）
        // ラインを購入すると1レコード追加される
        // ------------------------------------------------
        Schema::create('trx_reward_track_line', function (Blueprint $table) {
            $table->id()->comment('自動採番ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_reward_track_line_id')->comment('mst_reward_track_lineテーブルのID');
            $table->unsignedBigInteger('mst_in_app_purchase_id')->comment('購入した課金商品ID（購入履歴トレース用）');
            $table->dateTime('purchased_at')->comment('パス購入日時');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // 1プレイヤー × 1ラインで一意（同じラインは重複購入不可）
            $table->unique(['sys_player_id', 'mst_reward_track_line_id'], 'uq_player_line');
            $table->index('sys_player_id');
            $table->index('mst_reward_track_line_id');
            $table->index('is_delete');
        });

        // ------------------------------------------------
        // trx_reward_track_milestone（プレイヤーの受け取り済みマイルストーン）
        // 手動受け取り時に1レコード追加される
        // 履歴は永久保持（is_delete では削除しない）
        // ------------------------------------------------
        Schema::create('trx_reward_track_milestone', function (Blueprint $table) {
            $table->id()->comment('自動採番ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_reward_track_milestone_id')->comment('mst_reward_track_milestoneテーブルのID');
            $table->string('mst_reward_track_line_id')->comment('受け取ったラインのID');
            $table->dateTime('received_at')->comment('受け取り日時');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // 1プレイヤー × 1マイルストーン × 1ラインで一意（二重受け取り防止）
            $table->unique(
                ['sys_player_id', 'mst_reward_track_milestone_id', 'mst_reward_track_line_id'],
                'uq_player_milestone_line'
            );
            $table->index('sys_player_id');
            $table->index(['sys_player_id', 'mst_reward_track_milestone_id'], 'idx_player_milestone');
            $table->index('mst_reward_track_line_id');
            $table->index('is_delete');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_reward_track_milestone');
        Schema::dropIfExists('trx_reward_track_line');
        Schema::dropIfExists('trx_reward_track');
    }
};
