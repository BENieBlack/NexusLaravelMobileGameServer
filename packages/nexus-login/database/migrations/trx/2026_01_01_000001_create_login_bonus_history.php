<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ログインボーナスの最新受取状態テーブル
     * シャーディング対応（trx1, trx2...）
     */
    public function up(): void
    {
        Schema::create('trx_login_bonus', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');
            $table->enum('type', ['daily', 'comeback'])->comment('ログインボーナスタイプ');
            $table->string('mst_login_bonus_id')->comment('ログインボーナスID');
            $table->unsignedInteger('day')->default(0)->comment('最後に受け取った日数');
            $table->unsignedInteger('absent_days')
                ->nullable()
                ->comment('休眠日数（カムバックボーナスの場合のみ）');
            $table->dateTime('received_date')->comment('最後に受け取った日時（UTC）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'type'], 'pk_login_bonus');
            $table->index('mst_login_bonus_id');
            $table->index('received_date');
            $table->index('absent_days', 'idx_absent_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_login_bonus');
    }
};
