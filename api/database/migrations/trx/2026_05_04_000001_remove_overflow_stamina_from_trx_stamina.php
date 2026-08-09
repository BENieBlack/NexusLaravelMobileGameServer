<?php

use Illuminate\Database\Migrations\Migration;
use NexusPitr\Migrations\DynamicShardingTrait;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trx接続のシャード一覧
     * 
     * @var array<string>
     */
    private array $connections = $this->getTrxConnections();

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->getTrxConnections() as $connection) {
            if (Schema::connection($connection)->hasColumn('trx_stamina', 'overflow_stamina')) {
                Schema::connection($connection)->table('trx_stamina', function (Blueprint $table) {
                    $table->dropColumn('overflow_stamina');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->getTrxConnections() as $connection) {
            Schema::connection($connection)->table('trx_stamina', function (Blueprint $table) {
                $table->unsignedInteger('overflow_stamina')->default(0)->comment('オーバーフロースタミナ（最大値超過分）')->after('current_stamina');
            });
        }
    }
};
