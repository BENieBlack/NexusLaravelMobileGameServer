<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * アルバムに載せる対象かどうかをマスター側で持つ
     *
     * 例えばアイテムは回復薬のような消耗品も含むため、全件を対象にすると
     * 収集率が意味を持たなくなる。何を載せるかは運用で決められるようにする。
     */
    private const TABLES = ['mst_unit', 'mst_equipment', 'mst_item'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::connection('mst')->hasTable($tableName)) {
                continue;
            }

            if (Schema::connection('mst')->hasColumn($tableName, 'is_album_target')) {
                continue;
            }

            Schema::connection('mst')->table($tableName, function (Blueprint $table) {
                $table->boolean('is_album_target')->default(true)
                    ->comment('アルバム（図鑑）に載せる対象か');

                $table->index('is_album_target');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::connection('mst')->hasColumn($tableName, 'is_album_target')) {
                continue;
            }

            Schema::connection('mst')->table($tableName, function (Blueprint $table) {
                $table->dropIndex(['is_album_target']);
                $table->dropColumn('is_album_target');
            });
        }
    }
};
