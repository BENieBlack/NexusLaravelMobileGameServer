<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mst')->hasColumn('mst_in_app_purchase', 'purchase_limit_count')) {
            return;
        }

        Schema::connection('mst')->table('mst_in_app_purchase', function (Blueprint $table) {
            $table->unsignedInteger('purchase_limit_count')
                ->nullable()
                ->after('effect_duration_days');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('mst')->hasColumn('mst_in_app_purchase', 'purchase_limit_count')) {
            return;
        }

        Schema::connection('mst')->table('mst_in_app_purchase', function (Blueprint $table) {
            $table->dropColumn('purchase_limit_count');
        });
    }
};
