<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // sys_guild: ギルド情報
        // ========================================
        Schema::connection('sys')->create('sys_guild', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('ギルド名');
            $table->text('description')->nullable()->comment('ギルド説明');
            $table->unsignedInteger('level')->default(1)->comment('ギルドレベル');
            $table->unsignedBigInteger('exp')->default(0)->comment('ギルド経験値');
            $table->unsignedInteger('max_members')->default(30)->comment('最大メンバー数');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('name');
            $table->index('level');
        });

        // ========================================
        // sys_guild_member: ギルドメンバー
        // ========================================
        Schema::connection('sys')->create('sys_guild_member', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_guild_id')->comment('sys_guildテーブルのID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->enum('role', ['master', 'sub_master', 'member'])->default('member')->comment('役職');
            $table->dateTime('joined_at')->comment('加入日時');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->unique(['sys_guild_id', 'sys_player_id']);
            $table->index('sys_guild_id');
            $table->index('sys_player_id');
            $table->index('role');
        });

        // ========================================
        // sys_guild_apply: ギルド加入申請
        // ========================================
        Schema::connection('sys')->create('sys_guild_apply', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_guild_id')->comment('sys_guildテーブルのID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->enum('status', ['applied', 'accepted', 'rejected'])->default('applied')->comment('ステータス');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('sys_guild_id');
            $table->index('sys_player_id');
            $table->index('status');
            $table->index(['sys_guild_id', 'sys_player_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_guild_apply');
        Schema::connection('sys')->dropIfExists('sys_guild_member');
        Schema::connection('sys')->dropIfExists('sys_guild');
    }
};
