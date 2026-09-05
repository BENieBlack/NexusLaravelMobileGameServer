<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tol_dashboard_access_cache
 *
 * log_access（log1/log2/log3）を日次集計したキャッシュテーブル。
 * ダッシュボードはこのテーブルのみを参照するため
 * シャード数に関係なく高速に表示できる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tool')->create('tol_dashboard_access_cache', function (Blueprint $table) {
            $table->id();
            $table->date('access_date')->unique()->comment('集計対象日');
            $table->unsignedInteger('total_count')->default(0)->comment('総アクセス数（全シャード合計）');
            $table->unsignedInteger('unique_users')->default(0)->comment('ユニークユーザー数');
            $table->unsignedInteger('error_count')->default(0)->comment('エラー数（status_code >= 400）');
            $table->dateTime('calculated_at')->comment('集計実行日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            $table->index('access_date');
            $table->index('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tool')->dropIfExists('tol_dashboard_access_cache');
    }
};
