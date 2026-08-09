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
        // sys_player: プレイヤー基本情報
        // ========================================
        Schema::connection('sys')->create('sys_player', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique()->comment('システム内部識別子（UUIDv4）');
            $table->string('my_id', 8)->unique()->comment('プレイヤーID（8桁英数、フレンド検索・問い合わせ両用）');
            $table->string('name', 100)->nullable()->comment('プレイヤー名（後で設定可能）');
            $table->unsignedInteger('level')->default(1)->comment('プレイヤーレベル');
            $table->unsignedBigInteger('level_exp')->default(0)->comment('レベル経験値（累積）');
            $table->dateTime('last_login_at')->nullable()->comment('最終ログイン日時（UTC）');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('uuid');
            $table->index('my_id');
            $table->index('level');
            $table->index('last_login_at');
        });

        // ========================================
        // sys_player_device: デバイス情報
        // ========================================
        Schema::connection('sys')->create('sys_player_device', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('uuid', 255)->unique()->comment('デバイス固有UUID（OSから取得）');
            $table->json('device_info')->nullable()->comment('デバイス情報 (os, os_version, model, app_version)');
            $table->dateTime('last_login_at')->nullable()->comment('最終ログイン日時');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('sys_player_id');
            $table->index('uuid');
        });

        // ========================================
        // sys_player_token: リフレッシュトークン管理
        // ========================================
        Schema::connection('sys')->create('sys_player_token', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('sys_player_device_id')->comment('sys_player_deviceテーブルのID');
            $table->string('refresh_token_hash', 64)->unique()->comment('リフレッシュトークンのSHA-256ハッシュ');
            $table->dateTime('expires_at')->comment('有効期限（30日）');
            $table->dateTime('revoked_at')->nullable()->comment('無効化日時');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('sys_player_id');
            $table->index('sys_player_device_id');
            $table->index('refresh_token_hash');
            $table->index('expires_at');
        });

        // ========================================
        // sys_sharding_node_player: プレイヤーとノードの紐付け
        // ========================================
        Schema::connection('sys')->create('sys_sharding_node_player', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->primary()->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('sys_sharding_node_id')->comment('割り当てられたシャーディングノードID');
            $table->dateTime('assigned_at')->useCurrent()->comment('割り当て日時');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス（外部キー制約は使用しない）
            $table->index('sys_sharding_node_id');
            $table->index('assigned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_sharding_node_player');
        Schema::connection('sys')->dropIfExists('sys_player_token');
        Schema::connection('sys')->dropIfExists('sys_player_device');
        Schema::connection('sys')->dropIfExists('sys_player');
    }
};
