<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * tol_dashboard_retention
 *
 * log_access から集計した継続率キャッシュテーブル。
 * cohort_date（初回アクセス日）ごとに D1〜D90 の継続率を保存する。
 * calculated_at が古い場合は再集計して上書きする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tool')->create('tol_dashboard_retention', function (Blueprint $table) {
            $table->id()->comment('自動採番ID');
            $table->date('cohort_date')->unique()->comment('コホート日（初回アクセス日）');
            $table->unsignedInteger('new_users')->default(0)->comment('新規ユーザー数');

            // 継続率（%）: NULLはまだ集計期間に達していないことを示す
            $table->decimal('d1',  5, 2)->nullable()->comment('1日後継続率(%)');
            $table->decimal('d2',  5, 2)->nullable()->comment('2日後継続率(%)');
            $table->decimal('d3',  5, 2)->nullable()->comment('3日後継続率(%)');
            $table->decimal('d4',  5, 2)->nullable()->comment('4日後継続率(%)');
            $table->decimal('d5',  5, 2)->nullable()->comment('5日後継続率(%)');
            $table->decimal('d6',  5, 2)->nullable()->comment('6日後継続率(%)');
            $table->decimal('d7',  5, 2)->nullable()->comment('7日後継続率(%)');
            $table->decimal('d14', 5, 2)->nullable()->comment('14日後継続率(%)');
            $table->decimal('d30', 5, 2)->nullable()->comment('30日後継続率(%)');
            $table->decimal('d60', 5, 2)->nullable()->comment('60日後継続率(%)');
            $table->decimal('d90', 5, 2)->nullable()->comment('90日後継続率(%)');

            $table->dateTime('calculated_at')->comment('集計実行日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));

            $table->index('cohort_date');
            $table->index('calculated_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tool')->dropIfExists('tol_dashboard_retention');
    }
};
