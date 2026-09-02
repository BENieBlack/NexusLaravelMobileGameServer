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

            // カテゴリ
            $table->enum('category', [
                'system', 'battle', 'alliance', 'friend',
                'trade', 'reward', 'personal',
            ])->default('system')->comment('メールカテゴリ');

            // 優先度
            $table->enum('priority', ['normal', 'important', 'urgent'])
                ->default('normal')->comment('優先度');

            // 送信者情報
            $table->enum('sender_type', ['system', 'player', 'alliance', 'npc'])
                ->default('system')->comment('送信者タイプ');
            // sender_type によって参照先が変わる多相参照。
            // Player は sys_player、Alliance はギルド、NPC はマスターを指す想定で、
            // sys と mst にまたがるため content_mst_id のような接頭辞は付けられない。
            //
            // 現状どこからも書き込んでおらず、Alliance / NPC のテーブルも未整備。
            // 送信者機能を作るときに sender_sys_player_id のような
            // 型ごとの列へ分けること（sys_friend_apply と同じ書き方）。
            $table->string('sender_id')->nullable()->comment('送信者ID（sender_typeで参照先が変わる。未使用）');

            // 有効期限（日数）
            $table->unsignedInteger('expires_in_days')->default(30)
                ->comment('有効期限（日数、0=無期限）');

            // アイコンURL
            $table->string('icon_url', 512)->nullable()
                ->comment('アイコン画像URL');

            // 一斉配信フラグ
            $table->boolean('is_bulk_distributable')->default(false)
                ->comment('一斉配信可能フラグ');

            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_message_id');
            $table->index('category');
            $table->index(['category', 'priority']);
        });

        // ========================================
        // mst_mailbox_content: メールボックスコンテンツ
        // ========================================
        Schema::connection('mst')->create('mst_mailbox_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_mailbox_id')->comment('メールボックスID');
            $table->enum('content_type', [
                'diamond', 'paid_diamond', 'item', 'unit', 'equipment',
                'gold', 'food', 'wood', 'stone', 'stamina',
                'experience', 'alliance_points', 'custom',
            ])->comment('コンテンツタイプ');
            $table->string('content_mst_id')->comment('コンテンツID');
            $table->json('content_option')->nullable()->comment('コンテンツオプション (例: {"grade":1, "level":5})');
            $table->unsignedInteger('content_quantity')->default(1)->comment('1配布あたりのコンテンツ数量');
            $table->unsignedInteger('amount')->default(1)->comment('配布回数（content_quantity × amount = 実際の配布量）');

            // レアリティ
            $table->enum('rarity', ['C', 'UC', 'R', 'SR', 'SSR', 'UR'])
                ->nullable()->comment('レアリティ（表示用）');

            // ハイライト表示
            $table->boolean('is_highlight')->default(false)
                ->comment('ハイライト表示フラグ');

            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_mailbox_id', 'content_type', 'content_mst_id'], 'pk_mailbox_content');
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
