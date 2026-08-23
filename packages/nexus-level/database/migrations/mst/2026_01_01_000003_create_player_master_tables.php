<?php

use Illuminate\Database\Migrations\Migration;
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
        // ========================================
        // mst_player_level: プレイヤーレベルマスター
        // ========================================
        Schema::connection('mst')->create('mst_player_level', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->unsignedInteger('level')->primary()->comment('レベル');
            $table->unsignedBigInteger('required_exp')->comment('このレベルに到達するために必要な累積経験値');
            $table->unsignedInteger('max_stamina')->comment('このレベルでの最大スタミナ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
            
            $table->index('deploy_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_player_level');
    }
};
