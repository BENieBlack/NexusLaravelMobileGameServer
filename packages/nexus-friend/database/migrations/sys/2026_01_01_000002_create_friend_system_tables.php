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
        // sys_friend_apply: フレンド申請
        // ========================================
        Schema::connection('sys')->create('sys_friend_apply', function (Blueprint $table) {
            $table->id()->comment('フレンド申請ID');
            $table->unsignedBigInteger('sender_sys_player_id')->comment('申請送信者のプレイヤーID');
            $table->unsignedBigInteger('receiver_sys_player_id')->comment('申請受信者のプレイヤーID');
            $table->enum('status', ['Applied', 'Accepted', 'Rejected', 'Deleted'])
                ->default('Applied')
                ->comment('ステータス');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス（外部キー制約は使用しない）
            $table->index('sender_sys_player_id');
            $table->index('receiver_sys_player_id');
            $table->index('status');
            $table->index(['receiver_sys_player_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_friend_apply');
    }
};
