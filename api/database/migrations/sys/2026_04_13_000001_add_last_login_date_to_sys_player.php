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
            $table->dateTime('last_login_at')->nullable()->after('level_exp')->comment('最終ログイン日時（UTC）');
            
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->table('sys_player', function (Blueprint $table) {
            $table->dropIndex(['last_login_at']);
            $table->dropColumn('last_login_at');
        });
    }
};
