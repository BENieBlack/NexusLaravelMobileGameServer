<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // sys_sharding: シャーディング設定
        // ========================================
        Schema::connection('sys')->create('sys_sharding', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('シャーディング設定名（例: trx_sharding）');
            $table->string('target')->comment('シャーディング対象（例: transaction）');
            $table->enum('strategy', ['hash', 'range', 'consistent'])
                ->default('hash')
                ->comment('シャーディング方式');
            $table->string('sharding_key')->comment('シャーディングキー（例: player_id）');
            $table->unsignedInteger('node_count')->comment('ノード数');
            $table->boolean('is_active')->default(true)->comment('アクティブ状態');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('target');
            $table->index('is_active');
        });

        // ========================================
        // sys_sharding_node: シャーディングノード
        // ========================================
        Schema::connection('sys')->create('sys_sharding_node', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_sharding_id')->comment('シャーディング設定ID');
            $table->string('node_name', 50)->comment('ノード名（例: node1, node2）');
            $table->unsignedTinyInteger('node_no')->comment('ノード番号（trx{node_no}で接続名を構築）');
            $table->integer('weight')->default(100)->comment('負荷分散用の重み（大きいほど優先）');
            $table->enum('status', ['active', 'inactive', 'maintenance'])
                ->default('active')
                ->comment('ノードステータス');
            $table->boolean('is_writable')->default(true)->comment('書き込み可能かどうか');
            $table->boolean('is_readable')->default(true)->comment('読み込み可能かどうか');
            $table->integer('max_connections')->default(100)->comment('最大同時接続数');
            $table->integer('current_player_count')->default(0)->comment('現在割り当てられているプレイヤー数');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('sys_sharding_id');
            $table->index('status');
            $table->unique(['sys_sharding_id', 'node_name'], 'uk_sharding_node_name');
            $table->unique(['sys_sharding_id', 'node_no'], 'uk_sharding_node_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_sharding_node');
        Schema::connection('sys')->dropIfExists('sys_sharding');
    }
};
