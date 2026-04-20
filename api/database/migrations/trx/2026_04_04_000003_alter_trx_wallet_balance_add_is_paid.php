<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        // 各シャードに対してテーブルを変更
        foreach ($this->connections as $connection) {
            Schema::connection($connection)->table('trx_wallet_balance', function (Blueprint $table) {
                // is_paidカラムを追加（無償=false、有償=true）
                $table->boolean('is_paid')->default(false)->comment('有償フラグ（true=有償、false=無償）')->after('mst_item_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 各シャードに対してテーブルを元に戻す
        foreach ($this->connections as $connection) {
            Schema::connection($connection)->table('trx_wallet_balance', function (Blueprint $table) {
                $table->dropColumn('is_paid');
            });
        }
    }
};
