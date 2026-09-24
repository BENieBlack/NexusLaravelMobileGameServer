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
     * sys_player_token は元々 nexus-core（当時の nexus-player）の
     * create_player_system_tables で作られていた。トークンの契約
     * （TokenModelInterface / TokenRepositoryInterface）はこのパッケージが
     * 持っているため、スキーマもこちらへ移した。
     *
     * 移設前に構築された環境には既にテーブルがあり、そちらでは
     * このマイグレーションが未実行として走る。作成済みなら何もしない。
     */
    public function up(): void
    {
        if (Schema::connection('sys')->hasTable('sys_player_token')) {
            return;
        }

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
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('sys_player_id');
            $table->index('sys_player_device_id');
            $table->index('refresh_token_hash');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_player_token');
    }
};
