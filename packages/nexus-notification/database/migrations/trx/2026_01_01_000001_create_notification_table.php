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
    public function up(): void
    {
        // ========================================
        // trx_notification: プレイヤー内ゲーム通知
        // ========================================
        // シャードDB（trx1, trx2, ...）に作成される
        // php artisan trx:migrate で全シャードに適用すること
        Schema::create('trx_notification', function (Blueprint $table) {
            $table->id()->comment('通知ID（オートインクリメント）');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');

            // 通知種別: NotificationType enumの value を格納
            $table->string('type')->comment('通知種別（例: mission_completed, friend_apply_received）');

            $table->string('title')->comment('通知タイトル');
            $table->text('body')->comment('通知本文');

            // 追加データ（ミッションID・フレンドIDなど）
            $table->json('payload')->nullable()->comment('追加データ（JSON）');

            $table->boolean('is_read')->default(false)->comment('既読フラグ');
            $table->dateTime('read_at')->nullable()->comment('既読日時');

            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // 未読通知一覧取得用
            $table->index(['sys_player_id', 'is_read', 'created_at']);
            // 通知種別フィルタ用
            $table->index(['sys_player_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_notification');
    }
};
