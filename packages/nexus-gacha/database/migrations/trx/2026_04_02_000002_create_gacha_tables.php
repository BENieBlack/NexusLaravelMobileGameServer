<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // trx_gacha_history: ガチャ実行履歴
        // プレイヤーのガチャ実行履歴を記録
        // ========================================
        Schema::create('trx_gacha_history', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('履歴ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_gacha_id')->comment('ガチャID');
            $table->unsignedInteger('draw_count')->comment('実行回数（1連、10連など）');
            $table->enum('cost_type', ['diamond', 'paid_diamond', 'item'])->comment('使用したコストタイプ');
            $table->string('cost_mst_id')->nullable()->comment('使用したコストID（itemの場合）');
            $table->unsignedInteger('cost_amount')->comment('使用したコスト量');
            $table->json('prizes')->comment('獲得した景品リスト（JSON配列）');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('sys_player_id');
            $table->index(['sys_player_id', 'mst_gacha_id']);
            $table->index('created_at');
        });

        // ========================================
        // trx_gacha: ガチャプレイヤー進行状況
        // プレイヤーごとのガチャ実行状況を記録
        // - 日次実行回数と最後にリセットした日時
        // - ステップアップガチャの進行状況と最後にリセットした日時
        //
        // リセットロジック:
        // - daily_reset_at < 今日の0時の場合、daily_draw_countを0にリセットしてdaily_reset_atを更新
        // - ガチャ期間が終了した場合、total_draw_countとcurrent_stepをリセットしてtotal_reset_atを更新
        // ========================================
        Schema::create('trx_gacha', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_gacha_id')->comment('ガチャID');
            $table->unsignedInteger('current_step')->default(1)->comment('現在のステップ番号（ステップアップガチャ用）');
            $table->unsignedInteger('daily_draw_count')->default(0)->comment('本日の実行回数');
            $table->dateTime('daily_reset_at')->nullable()->comment('日次カウントを最後にリセットした日時');
            $table->unsignedInteger('total_draw_count')->default(0)->comment('累計実行回数（ステップアップガチャ用）');
            $table->dateTime('total_reset_at')->nullable()->comment('累計カウントを最後にリセットした日時（ガチャ期間終了時など）');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('sys_player_id');

            // 採番idは持たず、業務上の一意をそのまま主キーにする
            $table->primary(['sys_player_id', 'mst_gacha_id'], 'pk_gacha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_gacha');
        Schema::dropIfExists('trx_gacha_history');
    }
};
