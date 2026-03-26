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
        // tol_master_status: マスターデータステータス管理
        // ========================================
        Schema::connection('tool')->create('tol_master_status', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->integer('deploy_key')->comment('デプロイキー');
            $table->enum('status', ['preparing', 'ready', 'active', 'archived'])->comment('ステータス');
            $table->text('note')->nullable()->comment('備考');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            $table->index('deploy_key');
            $table->index('status');
        });

        // ========================================
        // tol_asset_status: アセットステータス管理
        // ========================================
        Schema::connection('tool')->create('tol_asset_status', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->integer('deploy_key')->comment('デプロイキー');
            $table->string('asset_type', 50)->comment('アセットタイプ');
            $table->string('version', 50)->comment('バージョン');
            $table->enum('status', ['preparing', 'ready', 'active', 'archived'])->comment('ステータス');
            $table->string('cdn_url', 255)->nullable()->comment('CDN URL');
            $table->text('note')->nullable()->comment('備考');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            $table->index('deploy_key');
            $table->index('status');
        });

        // ========================================
        // tol_banner: バナー管理
        // ========================================
        Schema::connection('tool')->create('tol_banner', function (Blueprint $table) {
            $table->id()->comment('バナーID');
            $table->string('title')->comment('タイトル');
            $table->enum('banner_type', ['home', 'gacha', 'event', 'shop', 'news'])->comment('バナータイプ');
            $table->string('image_url')->comment('画像URL');
            $table->enum('link_type', ['external', 'internal', 'gacha', 'shop', 'event'])->comment('リンクタイプ');
            $table->string('link_url')->nullable()->comment('リンクURL');
            $table->integer('display_order')->default(0)->comment('表示順序');
            $table->dateTime('start_at')->nullable()->comment('開始日時');
            $table->dateTime('end_at')->nullable()->comment('終了日時');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            $table->index('banner_type');
            $table->index('display_order');
            $table->index('is_active');
        });

        // ========================================
        // tol_cache_control: キャッシュ管理
        // ========================================
        Schema::connection('tool')->create('tol_cache_control', function (Blueprint $table) {
            $table->id()->comment('ID');
            $table->string('cache_key')->comment('キャッシュキー');
            $table->enum('cache_type', ['player', 'master', 'ranking', 'all'])->comment('キャッシュタイプ');
            $table->unsignedBigInteger('target_id')->nullable()->comment('対象ID');
            $table->unsignedBigInteger('executed_by')->nullable()->comment('実行者ID');
            $table->dateTime('executed_at')->nullable()->comment('実行日時');
            $table->text('note')->nullable()->comment('備考');

            $table->index('cache_type');
            $table->index('executed_at');
        });

        // ========================================
        // tol_maintenance: メンテナンス管理
        // ========================================
        Schema::connection('tool')->create('tol_maintenance', function (Blueprint $table) {
            $table->id()->comment('メンテナンスID');
            $table->string('title')->comment('タイトル');
            $table->text('message')->comment('メッセージ');
            $table->dateTime('start_at')->comment('開始日時');
            $table->dateTime('end_at')->comment('終了日時');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedBigInteger('created_by')->nullable()->comment('作成者ID');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            $table->index('is_active');
        });

        // ========================================
        // tol_notice: お知らせ管理
        // ========================================
        Schema::connection('tool')->create('tol_notice', function (Blueprint $table) {
            $table->id()->comment('お知らせID');
            $table->string('title')->comment('タイトル');
            $table->text('content')->comment('内容');
            $table->enum('notice_type', ['maintenance', 'update', 'event', 'bug', 'other'])->comment('お知らせタイプ');
            $table->integer('priority')->default(0)->comment('優先度');
            $table->dateTime('start_at')->nullable()->comment('開始日時');
            $table->dateTime('end_at')->nullable()->comment('終了日時');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedBigInteger('created_by')->nullable()->comment('作成者ID');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            $table->index('notice_type');
            $table->index('priority');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tool')->dropIfExists('tol_notice');
        Schema::connection('tool')->dropIfExists('tol_maintenance');
        Schema::connection('tool')->dropIfExists('tol_cache_control');
        Schema::connection('tool')->dropIfExists('tol_banner');
        Schema::connection('tool')->dropIfExists('tol_asset_status');
        Schema::connection('tool')->dropIfExists('tol_master_status');
    }
};
