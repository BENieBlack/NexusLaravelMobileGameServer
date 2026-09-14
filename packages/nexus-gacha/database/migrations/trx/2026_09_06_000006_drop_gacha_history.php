<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('trx_gacha_history');
    }

    public function down(): void
    {
        // ガチャ履歴はlog_gachaで管理するため復元しない。
    }
};
