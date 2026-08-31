<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * サポートする言語コード
     */
    protected $supportedLanguages = ['ja', 'en', 'zh-TW', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'id', 'pt', 'bn', 'ru', 'de', 'ko'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // mst_unit: ユニットマスター
        // ========================================
        Schema::connection('mst')->create('mst_unit', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('ユニットID');
            $table->enum('type', ['Attack', 'Defense', 'Support'])->comment('ユニットタイプ');
            $table->enum('element', ['Fire', 'Water', 'Wind', 'Earth', 'Light', 'Dark'])->comment('属性');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('type');
            $table->index('element');
            $table->index('rarity');
        });

        // ========================================
        // mst_unit__l10n: ユニット多言語
        // ========================================
        Schema::connection('mst')->create('mst_unit__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_unit_id')->comment('ユニットID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('ユニット名');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_unit_id', 'language'], 'pk_unit_language');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_item: アイテムマスター
        // ========================================
        Schema::connection('mst')->create('mst_item', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('アイテムID');
            $table->string('type')->comment('アイテムタイプ');
            $table->string('effect')->comment('効果');
            $table->float('value')->comment('効果値');

            // 残高として持つアイテム（gold, coin, 各種ポイントなど）。
            // trueなら trx_item ではなく trx_wallet 系で管理し、
            // 取得単位の有効期限と先入先出の消費ができる
            $table->boolean('is_wallet')->default(false)->comment('Wallet管理フラグ');

            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('is_wallet');
        });

        // ========================================
        // mst_item__l10n: アイテム多言語
        // ========================================
        Schema::connection('mst')->create('mst_item__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_item_id')->comment('アイテムID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('アイテム名');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_item_id', 'language'], 'pk_item_language');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_equipment: 装備マスター
        // ========================================
        Schema::connection('mst')->create('mst_equipment', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('装備ID');
            $table->enum('type', ['Attack', 'Defense', 'Support'])->comment('装備タイプ');
            $table->enum('element', ['Fire', 'Water', 'Wind', 'Earth', 'Light', 'Dark'])->comment('属性');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->unsignedInteger('attack')->default(0)->comment('攻撃力');
            $table->unsignedInteger('defense')->default(0)->comment('防御力');
            $table->unsignedInteger('hp')->default(0)->comment('HP');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('type');
            $table->index('element');
            $table->index('rarity');
            $table->index('is_active');
            $table->index('sort_desc');
        });

        // ========================================
        // mst_equipment__l10n: 装備多言語
        // ========================================
        Schema::connection('mst')->create('mst_equipment__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_equipment_id')->comment('装備ID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('装備名');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_equipment_id', 'language'], 'pk_equipment_language');
            $table->index('deploy_key');
            $table->index('language');
        });

        // ========================================
        // mst_unit_level: ユニットレベルマスター
        // ========================================
        Schema::connection('mst')->create('mst_unit_level', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedInteger('required_exp')->default(0)->comment('このレベルに到達するために必要な累積経験値');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['rarity', 'level'], 'pk_unit_level');
            $table->index('deploy_key');
            $table->index(['rarity', 'level']);
        });

        // ========================================
        // mst_equipment_level: 装備レベルマスター
        // ========================================
        Schema::connection('mst')->create('mst_equipment_level', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->enum('rarity', ['UR', 'SSR', 'SR', 'R', 'UC', 'C'])->comment('レアリティ');
            $table->unsignedInteger('level')->comment('レベル');
            $table->unsignedInteger('required_exp')->default(0)->comment('このレベルに到達するために必要な累積経験値');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['rarity', 'level'], 'pk_equipment_level');
            $table->index('deploy_key');
            $table->index(['rarity', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_equipment_level');
        Schema::connection('mst')->dropIfExists('mst_unit_level');
        Schema::connection('mst')->dropIfExists('mst_equipment__l10n');
        Schema::connection('mst')->dropIfExists('mst_equipment');
        Schema::connection('mst')->dropIfExists('mst_item__l10n');
        Schema::connection('mst')->dropIfExists('mst_item');
        Schema::connection('mst')->dropIfExists('mst_unit__l10n');
        Schema::connection('mst')->dropIfExists('mst_unit');
    }
};
