<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TrxDB用プレイヤーテーブル作成マイグレーション
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
        // trx_player: プレイヤートランザクションデータ
        // ========================================
        Schema::create('trx_player', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->primary()->comment('sys_playerテーブルのID');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
        });

        // ========================================
        // trx_player_sns: SNSアカウント連携情報
        // ========================================
        Schema::create('trx_player_sns', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->enum('sns_type', ['apple', 'google', 'x', 'facebook'])->comment('SNSタイプ');
            $table->string('sns_user_id')->comment('SNSユーザーID');
            $table->string('auth')->comment('認証情報');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['sys_player_id', 'sns_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_player_sns');
        Schema::dropIfExists('trx_player');
    }
};
