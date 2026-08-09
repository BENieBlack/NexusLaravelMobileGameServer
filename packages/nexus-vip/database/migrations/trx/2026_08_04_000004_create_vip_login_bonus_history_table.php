<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * VIPログインボーナス受け取り履歴テーブル
     * シャーディング対応（trx1, trx2...）
     */
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connections = $this->getTrxConnections();

        foreach ($connections as $connection) {
    Schema::dropIfExists('trx_vip_login_bonus_history');
    }
};
