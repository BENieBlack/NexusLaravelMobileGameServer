<?php

use Illuminate\Database\Migrations\Migration;
use NexusPitr\Migrations\DynamicShardingTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DynamicShardingTrait;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 各シャードに対してテーブルを変更
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->table('trx_wallet', function (Blueprint $table) {
                // amount列を削除
                $table->dropColumn('amount');
                
                // free_amount, paid_amount列を追加
                $table->unsignedInteger('free_amount')->default(0)->comment('無償通貨数')->after('mst_item_id');
                $table->unsignedInteger('paid_amount')->default(0)->comment('有償通貨数')->after('free_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 各シャードに対してテーブルを元に戻す
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->table('trx_wallet', function (Blueprint $table) {
                // free_amount, paid_amount列を削除
                $table->dropColumn(['free_amount', 'paid_amount']);
                
                // amount列を追加
                $table->unsignedInteger('amount')->default(0)->comment('現在の残高')->after('mst_item_id');
            });
        }
    }
};
