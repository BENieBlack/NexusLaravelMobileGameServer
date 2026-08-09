<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TrxDB用リソーステーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan trx:migrate`で実行してください。
 * TrxMigrateCommandが全TrxDBシャード（trx1, trx2, ...）に対して自動的に実行します。
 */
return new class extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // trx_unit: プレイヤー所持ユニット
        // ========================================
        Schema::create('trx_unit', function (Blueprint $table) {
            $table->id()->comment('ユニット所持ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_unit_id')->comment('マスターユニットID');
            $table->unsignedInteger('grade')->comment('グレード');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedBigInteger('level_exp')->default(0)->comment('現在のレベルの経験値');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'mst_unit_id']);
        });

        // ========================================
        // trx_equipment: プレイヤー所持装備
        // ========================================
        Schema::create('trx_equipment', function (Blueprint $table) {
            $table->id()->comment('装備所持ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_equipment_id')->comment('マスター装備ID');
            $table->unsignedInteger('grade')->comment('グレード');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedBigInteger('level_exp')->default(0)->comment('レベル経験値');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index(['sys_player_id', 'mst_equipment_id']);
        });

        // ========================================
        // trx_item: プレイヤー所持アイテム
        // ========================================
        Schema::create('trx_item', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_item_id')->comment('マスターアイテムID');
            $table->unsignedInteger('free_amount')->default(0)->comment('無償アイテム数');
            $table->unsignedInteger('paid_amount')->default(0)->comment('有償アイテム数');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'mst_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_item');
        Schema::dropIfExists('trx_equipment');
        Schema::dropIfExists('trx_unit');
    }
};
