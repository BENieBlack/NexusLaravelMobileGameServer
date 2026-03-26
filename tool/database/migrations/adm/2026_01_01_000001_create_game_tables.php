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
        // adm_account: 管理者アカウント
        // ========================================
        Schema::connection('admin')->create('adm_account', function (Blueprint $table) {
            $table->id()->comment('管理者ID');
            $table->string('name')->comment('管理者名');
            $table->string('email')->unique()->comment('メールアドレス');
            $table->dateTime('email_verified_at')->nullable()->comment('メール確認日時');
            $table->string('password')->comment('パスワード');
            $table->rememberToken()->comment('ログイン記憶トークン');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');
        });

        // ========================================
        // sessions: セッション管理
        // ========================================
        Schema::connection('admin')->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('セッションID');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('ユーザーID');
            $table->string('ip_address', 45)->nullable()->comment('IPアドレス');
            $table->text('user_agent')->nullable()->comment('ユーザーエージェント');
            $table->longText('payload')->comment('セッションデータ');
            $table->integer('last_activity')->index()->comment('最終アクティビティ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('admin')->dropIfExists('sessions');
        Schema::connection('admin')->dropIfExists('adm_account');
    }
};
