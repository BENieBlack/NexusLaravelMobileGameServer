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
     * チャットデータはシャードをまたいで参照されるため sys DB に作成する
     * （ギルドチャット・グループチャットは異なるシャードのプレイヤー同士が参加する）
     */
    public function up(): void
    {
        // ========================================
        // sys_chat_room: チャットルーム
        // ========================================
        Schema::create('sys_chat_room', function (Blueprint $table) {
            $table->id()->comment('チャットルームID');

            // 'friend' | 'guild' | 'group'
            $table->string('type', 20)->comment('ルーム種別');

            // フレンドDM:     "{小さいID}_{大きいID}"
            // ギルドチャット: "{guild_id}"
            // グループ:       "{chat_room_id}" （作成後に自己参照で更新）
            $table->string('room_key', 100)->unique()->comment('ルーム一意キー');

            // グループ用の表示名（FRIEND/GUILDはNULL）
            $table->string('name', 100)->nullable()->comment('グループ名');

            // GUILDチャット用のギルドID
            $table->unsignedBigInteger('sys_guild_id')->nullable()->comment('ギルドID（guild タイプのみ）');

            // デノーマライズしたメンバー数（group チャットの満員チェックに使用）
            $table->unsignedSmallInteger('member_count')->default(0)->comment('メンバー数');

            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('type');
            $table->index('sys_guild_id');
        });

        // ========================================
        // sys_chat_room_member: グループチャットメンバー
        // ========================================
        // FRIEND / GUILD タイプでは使用しない
        // （FRIENDは room_key で2人を管理、GUILDはギルドメンバーシップで管理）
        Schema::create('sys_chat_room_member', function (Blueprint $table) {
            $table->id()->comment('メンバーID');
            $table->unsignedBigInteger('chat_room_id')->comment('チャットルームID');
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID');

            // 表示名を非正規化（プレイヤー名変更に追従しない仕様）
            $table->string('player_name', 50)->comment('プレイヤー表示名（参加時点）');

            // 'owner' | 'admin' | 'member'
            $table->string('role', 20)->default('member')->comment('ロール');

            $table->dateTime('joined_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('参加日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->unique(['chat_room_id', 'sys_player_id']);
            // プレイヤーが参加しているルーム一覧取得用
            $table->index(['sys_player_id', 'chat_room_id']);
        });

        // ========================================
        // sys_chat_message: チャットメッセージ
        // ========================================
        Schema::create('sys_chat_message', function (Blueprint $table) {
            $table->id()->comment('メッセージID（カーソルページネーション用）');
            $table->unsignedBigInteger('chat_room_id')->comment('チャットルームID');
            $table->unsignedBigInteger('sender_player_id')->comment('送信者プレイヤーID');

            // 表示名を非正規化（プレイヤー名変更時も送信時の名前を表示する）
            $table->string('sender_name', 50)->comment('送信者表示名（送信時点）');

            $table->text('body')->comment('メッセージ本文（最大500文字）');

            $table->boolean('is_deleted')->default(false)->comment('論理削除フラグ');

            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('送信日時');

            // ルームのメッセージ履歴取得（カーソルページネーション）
            $table->index(['chat_room_id', 'id']);
            // 送信者のメッセージ削除
            $table->index(['chat_room_id', 'sender_player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_chat_message');
        Schema::dropIfExists('sys_chat_room_member');
        Schema::dropIfExists('sys_chat_room');
    }
};
