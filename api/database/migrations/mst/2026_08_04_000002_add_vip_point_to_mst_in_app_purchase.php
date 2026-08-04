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
        Schema::connection('mst')->table('mst_in_app_purchase', function (Blueprint $table) {
            $table->unsignedInteger('vip_point')
                ->default(0)
                ->after('paid_diamond_amount')
                ->comment('付与VIPポイント');
            
            $table->index('vip_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->table('mst_in_app_purchase', function (Blueprint $table) {
            $table->dropIndex(['vip_point']);
            $table->dropColumn('vip_point');
        });
    }
};
