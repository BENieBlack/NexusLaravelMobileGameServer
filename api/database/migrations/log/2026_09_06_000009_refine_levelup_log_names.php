<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'log_action_unit_change' => 'log_action_unit_levelup',
            'log_action_equipment_change' => 'log_action_equipment_levelup',
        ] as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        // Renames are intentionally not reversed automatically.
    }
};
