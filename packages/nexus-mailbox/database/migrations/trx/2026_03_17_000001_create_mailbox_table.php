<?php

use Illuminate\Database\Migrations\Migration;
use NexusPitr\Migrations\DynamicShardingTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DynamicShardingTrait;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 各シャードに対してテーブルを作成
        foreach ($this->getTrxConnections() as $connection) {
            $this->createTablesForConnection($connection);
        }
    }

    /**
     * 指定された接続に対してテーブルを作成
     */
    protected function createTablesForConnection(string $connection): void
    {
        // ========================================
        // trx_mailbox: プレイヤーメールボックス
        // ========================================
        Schema::connection($connection)->create('trx_mailbox', function (Blueprint $table) {
            $table->id()->comment('メールボックスID（オートインクリメント）');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_mailbox_id')->comment('mst_mailboxテーブルのID');
            $table->boolean('is_opened')->default(false)->comment('既読フラグ');
            $table->boolean('is_received')->default(false)->comment('受取済みフラグ');
            
            // 保護フラグ
            $table->boolean('is_protected')->default(false)
                ->comment('保護フラグ（削除防止）');
            
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            
            // 有効期限
            $table->dateTime('expires_at')->nullable()
                ->comment('有効期限（NULL=無期限）');
            
            // 既読日時
            $table->dateTime('read_at')->nullable()
                ->comment('既読日時');
            
            // 受取日時
            $table->dateTime('received_at')->nullable()
                ->comment('受取日時');
            
            // 送信者名（動的）
            $table->string('sender_name')->nullable()
                ->comment('送信者名（プレイヤー名など動的に設定）');
            
            // カスタムパラメータ（JSONプレースホルダー置換用）
            $table->json('custom_params')->nullable()
                ->comment('カスタムパラメータ（テンプレート置換用）');
            
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'is_delete']);
            $table->index(['sys_player_id', 'is_opened', 'is_received']);
            $table->index('mst_mailbox_id');
        });
        
        // インデックス追加
        DB::connection($connection)->statement("
            CREATE INDEX idx_expires_at ON trx_mailbox(sys_player_id, expires_at);
        ");
        
        DB::connection($connection)->statement("
            CREATE INDEX idx_protected ON trx_mailbox(sys_player_id, is_protected, is_delete);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->dropIfExists('trx_mailbox');
        }
    }
};
