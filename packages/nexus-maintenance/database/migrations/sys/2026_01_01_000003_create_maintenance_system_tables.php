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
        // sys_maintenance: メンテナンス管理
        // ========================================
        Schema::connection('sys')->create('sys_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->comment('メンテナンスタイトル');
            $table->text('message')->comment('メンテナンスメッセージ');
            $table->dateTime('start_at')->comment('メンテナンス開始日時');
            $table->dateTime('end_at')->nullable()->comment('メンテナンス終了日時');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('start_at');
            $table->index('end_at');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_maintenance');
    }
};
