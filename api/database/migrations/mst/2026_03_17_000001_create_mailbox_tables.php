<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * サポートする言語コード
     */
    protected $supportedLanguages = ['ja', 'en', 'zh-TW', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'id', 'pt', 'bn', 'ru', 'de', 'ko'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // mst_message: メッセージマスター
        // ========================================
        Schema::connection('mst')->create('mst_message', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('メッセージID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
        });

        // ========================================
        // mst_message__i18n: メッセージ多言語
        // ========================================
        Schema::connection('mst')->create('mst_message__i18n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_message_id')->comment('メッセージID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('title')->comment('タイトル');
            $table->text('body')->comment('本文');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_message_id', 'language'], 'pk_message_language');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_mailbox: メールボックスマスター
        // ========================================
        Schema::connection('mst')->create('mst_mailbox', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('メールボックスID');
            $table->string('mst_message_id')->comment('メッセージID');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_message_id');
        });

        // ========================================
        // mst_mailbox_content: メールボックスコンテンツ
        // ========================================
        Schema::connection('mst')->create('mst_mailbox_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_mailbox_id')->comment('メールボックスID');
            $table->enum('content_type', ['Diamond', 'Item', 'Unit', 'Equipment'])->comment('コンテンツタイプ');
            $table->string('content_id')->comment('コンテンツID');
            $table->unsignedInteger('amount')->default(1)->comment('数量');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_mailbox_id', 'content_type', 'content_id'], 'pk_mailbox_content');
            $table->index('deploy_key');
            $table->index('mst_mailbox_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_mailbox_content');
        Schema::connection('mst')->dropIfExists('mst_mailbox');
        Schema::connection('mst')->dropIfExists('mst_message__i18n');
        Schema::connection('mst')->dropIfExists('mst_message');
    }
};
