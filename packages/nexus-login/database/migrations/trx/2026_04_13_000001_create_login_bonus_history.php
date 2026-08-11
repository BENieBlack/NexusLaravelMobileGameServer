<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ログインボーナス受け取り履歴テーブル
     * シャーディング対応（trx1, trx2...）
     */
    public function up(): void
    {
        Schema::create('trx_login_bonus_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->string('mst_login_bonus_id')->comment('ログインボーナスID');
            $table->unsignedInteger('absent_days')
                ->nullable()
                ->comment('休眠日数（カムバックボーナスの場合のみ）');
            $table->date('received_date')->comment('受け取った日付（UTC）');
            $table->enum('reward_type', ['item', 'unit', 'equipment', 'wallet', 'diamond'])->comment('報酬タイプ');
            $table->string('reward_id')->comment('報酬ID');
            $table->unsignedInteger('reward_amount')->comment('報酬数量');
            $table->boolean('is_paid')->default(false)->comment('有償フラグ');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            $table->index('sys_player_id');
            $table->index('received_date');
            $table->index(['sys_player_id', 'received_date']);
            $table->index('absent_days', 'idx_absent_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_login_bonus_history');
    }
};
