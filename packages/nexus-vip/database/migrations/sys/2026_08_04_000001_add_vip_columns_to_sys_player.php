<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('sys')->table('sys_player', function (Blueprint $table) {
            $table->unsignedTinyInteger('vip_level')->default(0)->after('level_exp')->comment('VIPレベル（0～10）');
            $table->unsignedInteger('vip_point')->default(0)->after('vip_level')->comment('累積VIPポイント');
            $table->decimal('total_paid_amount', 15, 2)->default(0.00)->after('vip_point')->comment('累積課金額（日本円換算）');
            
            // インデックス追加
            $table->index('vip_level');
            $table->index('vip_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->table('sys_player', function (Blueprint $table) {
            // カラムが存在する場合のみ削除（インデックスも自動的に削除される）
            if (Schema::connection('sys')->hasColumn('sys_player', 'vip_level')) {
                $table->dropColumn('vip_level');
            }
            if (Schema::connection('sys')->hasColumn('sys_player', 'vip_point')) {
                $table->dropColumn('vip_point');
            }
            if (Schema::connection('sys')->hasColumn('sys_player', 'total_paid_amount')) {
                $table->dropColumn('total_paid_amount');
            }
        });
    }
};
