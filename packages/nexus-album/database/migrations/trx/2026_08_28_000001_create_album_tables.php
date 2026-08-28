<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // trx_album: プレイヤーが一度でも入手・解放した対象の記録
        //
        // 手放しても記録は残るため、所持テーブル（trx_unit等）とは別に持つ。
        // 数量は持たず、(sys_player_id, type, master_id) で一意。
        // ========================================
        Schema::create('trx_album', function (Blueprint $table) {
            $table->id()->comment('アルバム記録ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->enum('type', ['unit', 'equipment', 'item'])->comment('記録対象の種別');
            $table->string('master_id')->comment('マスターID（mst_unit.id など）');
            $table->dateTime('unlocked_at')->comment('初めて入手・解放した日時');
            $table->boolean('is_delete')->default(false)->comment('論理削除フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // 同じ対象を二重に記録しない
            $table->unique(['sys_player_id', 'type', 'master_id'], 'uk_player_type_master');
            $table->index(['sys_player_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_album');
    }
};
