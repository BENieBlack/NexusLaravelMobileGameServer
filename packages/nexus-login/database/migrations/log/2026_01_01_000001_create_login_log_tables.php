<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用ログインボーナスログテーブル作成マイグレーション
 *
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 */
return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // log_action_login_bonus_receive: ログインボーナス受取ログ
        // ========================================
        Schema::create('log_action_login_bonus_receive', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->enum('type', ['daily', 'comeback'])->comment('ログインボーナスタイプ');
            $table->string('mst_login_bonus_id')->comment('ログインボーナスID');
            $table->unsignedInteger('day')->default(0)->comment('受け取った日数');
            $table->unsignedInteger('absent_days')->nullable()->comment('休眠日数');
            $table->dateTime('received_at')->comment('受け取り日時');
            $table->string('reward_type')->comment('実際に増えたリソース種別');
            $table->string('reward_mst_id')->comment('実際に増えたリソースID');
            $table->unsignedInteger('reward_amount')->comment('実際に増えた数量');
            $table->boolean('is_paid')->default(false)->comment('有償フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('ログ作成日時');

            $table->index('sys_player_id');
            $table->index('mst_login_bonus_id');
            $table->index('received_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_action_login_bonus_receive');
    }
};
