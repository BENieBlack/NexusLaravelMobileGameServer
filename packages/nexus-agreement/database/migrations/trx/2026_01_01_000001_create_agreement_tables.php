<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * プレイヤーの利用規約同意記録テーブル（trxDB）
     * insert only — 同意記録は不変の監査ログとして扱う
     */
    public function up(): void
    {
        // ========================================
        // trx_agreement: プレイヤーの同意記録
        // ========================================
        Schema::create('trx_agreement', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->comment('プレイヤーID（sys_playerテーブルのID）');
            $table->unsignedBigInteger('sys_agreement_id')->comment('同意した規約ID（sys_agreementテーブルのID）');
            $table->dateTime('consented_at')->comment('同意日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');

            // 主キー（複合）
            $table->primary(['sys_player_id', 'sys_agreement_id'], 'pk_agreement');

            // インデックス（外部キー制約は使用しない）
            $table->index('sys_agreement_id');
            $table->index('consented_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_agreement');
    }
};
