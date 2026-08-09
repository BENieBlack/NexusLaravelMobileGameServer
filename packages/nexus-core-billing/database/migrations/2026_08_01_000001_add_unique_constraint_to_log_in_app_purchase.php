<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LogDB用課金ログテーブルにユニーク制約追加マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 * PitrMigrateCommandが全LogDBシャード（log1, log2, ...）に対して自動的に実行します。
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('log_in_app_purchase', function (Blueprint $table) {
            // receipt_id にユニーク制約を追加（二重課金ログ防止）
            $table->unique('receipt_id', 'unique_receipt_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_in_app_purchase', function (Blueprint $table) {
            $table->dropUnique('unique_receipt_id');
        });
    }
};
