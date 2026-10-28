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
     * 利用規約管理テーブル（sysDB）
     */
    public function up(): void
    {
        // ========================================
        // sys_agreement: 規約バージョン管理
        // ========================================
        Schema::create('sys_agreement', function (Blueprint $table) {
            $table->id()->comment('規約ID');
            $table->string('version', 20)->unique()->comment('バージョン番号（例: 1.0.0）');
            $table->string('title', 255)->comment('規約タイトル（管理用）');
            $table->string('url', 500)->comment('規約本文URL');
            $table->boolean('is_required')->default(true)->comment('同意必須フラグ（falseは任意同意）');
            $table->dateTime('published_at')->comment('公開日時（この日時以降に初回ログインしたプレイヤーに表示）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('published_at');
            $table->index('is_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sys_agreement');
    }
};
