<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_action_gacha_draw', function (Blueprint $table): void {
            if (! Schema::hasColumn('log_action_gacha_draw', 'draw_count')) {
                $table->unsignedInteger('draw_count')->default(1)->after('mst_gacha_id');
                $table->string('cost_type')->default('diamond')->after('draw_count');
                $table->string('cost_mst_id')->nullable()->after('cost_type');
                $table->unsignedInteger('cost_amount')->default(0)->after('cost_mst_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('log_action_gacha_draw', function (Blueprint $table): void {
            $table->dropColumn(['draw_count', 'cost_type', 'cost_mst_id', 'cost_amount']);
        });
    }
};
