<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ギルドチャットの既読管理テーブル。
     * プレイヤーごとにギルドチャットの最後に読んだメッセージIDを記録する。
     * last_read_message_id が NULL の場合は未読（まだ一度も読んでいない）を表す。
     */
    public function up(): void
    {
        Schema::create('sys_guild_chat_read', function (Blueprint $table) {
            // プレイヤーID（複合PK）
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');

            // ギルドID（複合PK）
            $table->unsignedBigInteger('sys_guild_id')->comment('ギルドID');

            // 最後に既読にしたメッセージID（NULL=未読）
            $table->unsignedBigInteger('last_read_message_id')->nullable()->comment('最後に既読にしたメッセージID（NULL=未読）');

            $table->dateTime('updated_at')->comment('更新日時');

            // 複合主キー
            $table->primary(['sys_player_id', 'sys_guild_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_guild_chat_read');
    }
};
