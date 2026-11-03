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
     * 認証で使うテーブル（トークン、引き継ぎコード、外部アカウントの紐付け）を作る。
     *
     * sys_player_token は元々 nexus-core（当時の nexus-player）の
     * create_player_system_tables で作られていた。トークンの契約
     * （TokenModelInterface / TokenRepositoryInterface）はこのパッケージが
     * 持っているため、スキーマもこちらへ移した。
     *
     * 移設前に構築された環境には既にテーブルがあり、そちらでは
     * このマイグレーションが未実行として走る。作成済みなら作らない。
     */
    public function up(): void
    {
        $this->createPlayerTokenTable();

        // ========================================
        // sys_player_transfer_code: 引き継ぎコード（プレイヤーごとに1行。発行し直すと上書き）
        // ========================================
        Schema::create('sys_player_transfer_code', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->unique()->comment('sys_playerテーブルのID');
            $table->string('transfer_code', 32)->unique()->comment('引き継ぎコード');
            $table->string('password_hash', 255)->comment('引き継ぎパスワードのハッシュ（password_hash）');
            $table->unsignedInteger('failed_count')->default(0)->comment('パスワードを続けて間違えた回数');
            $table->dateTime('locked_until')->nullable()->comment('この日時まで引き継ぎを受け付けない');
            $table->dateTime('expires_at')->comment('有効期限');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
        });

        // ========================================
        // sys_player_external_account: 外部アカウント（Apple / Google）との紐付け
        // ========================================
        Schema::create('sys_player_external_account', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('provider', 32)->comment('外部アカウントの種類 (apple, google)');
            $table->string('provider_user_id', 255)->comment('外部アカウント側のユーザーID（IDトークンの sub）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->unique(['provider', 'provider_user_id'], 'uq_external_account_provider_user');
            $table->unique(['sys_player_id', 'provider'], 'uq_external_account_player_provider');
        });
    }

    /**
     * sys_player_token を作る（作成済みなら何もしない）
     */
    private function createPlayerTokenTable(): void
    {
        if (Schema::hasTable('sys_player_token')) {
            return;
        }

        // ========================================
        // sys_player_token: リフレッシュトークン管理
        // ========================================
        Schema::create('sys_player_token', function (Blueprint $table) {
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
        Schema::dropIfExists('sys_player_external_account');
        Schema::dropIfExists('sys_player_transfer_code');
        Schema::dropIfExists('sys_player_token');
    }
};
