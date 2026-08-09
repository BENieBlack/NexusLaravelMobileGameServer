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
     * メールボックスシステムの拡張
     */
    public function up(): void
    {
        // ========================================
        // mst_mailbox拡張
        // ========================================
        Schema::connection('mst')->table('mst_mailbox', function (Blueprint $table) {
            // カテゴリ
            $table->enum('category', [
                'System', 'Battle', 'Alliance', 'Friend', 
                'Trade', 'Reward', 'Personal'
            ])->default('System')->after('mst_message_id')->comment('メールカテゴリ');
            
            // 優先度
            $table->enum('priority', ['Normal', 'Important', 'Urgent'])
                ->default('Normal')->after('category')->comment('優先度');
            
            // 送信者情報
            $table->enum('sender_type', ['System', 'Player', 'Alliance', 'NPC'])
                ->default('System')->after('priority')->comment('送信者タイプ');
            $table->string('sender_id')->nullable()->after('sender_type')->comment('送信者ID');
            
            // 有効期限（日数）
            $table->unsignedInteger('expires_in_days')->default(30)->after('sender_id')
                ->comment('有効期限（日数、0=無期限）');
            
            // アイコンURL
            $table->string('icon_url', 512)->nullable()->after('expires_in_days')
                ->comment('アイコン画像URL');
            
            // 一斉配信フラグ
            $table->boolean('is_bulk_distributable')->default(false)->after('icon_url')
                ->comment('一斉配信可能フラグ');
            
            // インデックス
            $table->index('category');
            $table->index(['category', 'priority']);
        });

        // ========================================
        // mst_mailbox_content拡張
        // ========================================
        DB::connection('mst')->statement("
            ALTER TABLE mst_mailbox_content 
            MODIFY content_type ENUM(
                'Diamond', 'PaidDiamond', 'Item', 'Unit', 'Equipment',
                'Gold', 'Food', 'Wood', 'Stone', 'Stamina',
                'Experience', 'AlliancePoints', 'Custom'
            ) COMMENT 'コンテンツタイプ'
        ");

        Schema::connection('mst')->table('mst_mailbox_content', function (Blueprint $table) {
            // レアリティ
            $table->enum('rarity', ['C', 'UC', 'R', 'SR', 'SSR', 'UR'])
                ->nullable()->after('amount')->comment('レアリティ（表示用）');
            
            // ハイライト表示
            $table->boolean('is_highlight')->default(false)->after('rarity')
                ->comment('ハイライト表示フラグ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->table('mst_mailbox_content', function (Blueprint $table) {
            $table->dropColumn(['rarity', 'is_highlight']);
        });

        DB::connection('mst')->statement("
            ALTER TABLE mst_mailbox_content 
            MODIFY content_type ENUM('Diamond', 'Item', 'Unit', 'Equipment') 
            COMMENT 'コンテンツタイプ'
        ");

        Schema::connection('mst')->table('mst_mailbox', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['category', 'priority']);
            $table->dropColumn([
                'category', 'priority', 'sender_type', 'sender_id',
                'expires_in_days', 'icon_url', 'is_bulk_distributable'
            ]);
        });
    }
};
