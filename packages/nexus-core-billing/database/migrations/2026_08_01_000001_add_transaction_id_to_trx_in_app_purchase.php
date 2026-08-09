<?php

use Illuminate\Database\Migrations\Migration;
use NexusPitr\Migrations\DynamicShardingTrait;
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
        // トランザクションDB接続の一覧を取得
        $connections = $this->getTrxConnections();

        foreach ($connections as $connection) {
            Schema::connection($connection)->table('trx_in_app_purchase', function (Blueprint $table) {
                // transaction_id カラムを追加
                $table->string('transaction_id', 255)
                    ->nullable()
                    ->after('mst_in_app_purchase_id')
                    ->comment('プラットフォーム固有のトランザクションID（Apple: transaction_id, Google: orderId）');
                
                // ユニーク制約を追加（二重課金防止）
                // billing_platform + transaction_id の組み合わせでユニーク
                $table->unique(['billing_platform', 'transaction_id'], 'unique_transaction_per_platform');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connections = $this->getTrxConnections();

        foreach ($connections as $connection) {
            Schema::connection($connection)->table('trx_in_app_purchase', function (Blueprint $table) {
                $table->dropUnique('unique_transaction_per_platform');
                $table->dropColumn('transaction_id');
            });
        }
    }
};
