<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * シャーディング対象の接続名
     */
    protected $connections = ['trx1', 'trx2'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->connections as $connection) {
            $this->extendTableForConnection($connection);
        }
    }

    /**
     * 指定された接続に対してテーブルを拡張
     */
    protected function extendTableForConnection(string $connection): void
    {
        Schema::connection($connection)->table('trx_mailbox', function (Blueprint $table) {
            // 保護フラグ
            $table->boolean('is_protected')->default(false)->after('is_received')
                ->comment('保護フラグ（削除防止）');
            
            // 有効期限
            $table->dateTime('expires_at')->nullable()->after('is_protected')
                ->comment('有効期限（NULL=無期限）');
            
            // 既読日時
            $table->dateTime('read_at')->nullable()->after('expires_at')
                ->comment('既読日時');
            
            // 受取日時
            $table->dateTime('received_at')->nullable()->after('read_at')
                ->comment('受取日時');
            
            // 送信者名（動的）
            $table->string('sender_name')->nullable()->after('received_at')
                ->comment('送信者名（プレイヤー名など動的に設定）');
            
            // カスタムパラメータ（JSONプレースホルダー置換用）
            $table->json('custom_params')->nullable()->after('sender_name')
                ->comment('カスタムパラメータ（テンプレート置換用）');
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
        foreach ($this->connections as $connection) {
            DB::connection($connection)->statement("DROP INDEX idx_expires_at ON trx_mailbox");
            DB::connection($connection)->statement("DROP INDEX idx_protected ON trx_mailbox");
            
            Schema::connection($connection)->table('trx_mailbox', function (Blueprint $table) {
                $table->dropColumn([
                    'is_protected', 'expires_at', 'read_at', 
                    'received_at', 'sender_name', 'custom_params'
                ]);
            });
        }
    }
};
