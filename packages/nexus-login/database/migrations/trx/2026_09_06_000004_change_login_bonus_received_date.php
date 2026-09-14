<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE trx_login_bonus MODIFY received_date DATETIME NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trx_login_bonus MODIFY received_date DATE NOT NULL');
    }
};
