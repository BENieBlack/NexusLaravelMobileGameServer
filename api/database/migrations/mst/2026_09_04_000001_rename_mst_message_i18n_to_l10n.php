<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * mst_message__i18n を mst_message__l10n にリネームする
 *
 * 過去のマイグレーションで i18n という名前で作成されたが、
 * プロジェクト規約に従い l10n に統一する。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mst')->hasTable('mst_message__i18n')
            && !Schema::connection('mst')->hasTable('mst_message__l10n')) {
            Schema::connection('mst')->rename('mst_message__i18n', 'mst_message__l10n');
        }
    }

    public function down(): void
    {
        if (Schema::connection('mst')->hasTable('mst_message__l10n')
            && !Schema::connection('mst')->hasTable('mst_message__i18n')) {
            Schema::connection('mst')->rename('mst_message__l10n', 'mst_message__i18n');
        }
    }
};
