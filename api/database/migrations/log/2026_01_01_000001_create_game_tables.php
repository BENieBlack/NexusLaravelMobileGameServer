<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ========================================
        // log_access: アクセスログ
        // ========================================
        Schema::connection('log')->create('log_access', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->unique()->comment('リクエスト一意ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('method')->comment('HTTPメソッド');
            $table->string('endpoint')->comment('エンドポイント');
            $table->json('request_header')->nullable()->comment('リクエストヘッダー');
            $table->json('request_body')->nullable()->comment('リクエストボディ');
            $table->json('response_header')->nullable()->comment('レスポンスヘッダー');
            $table->json('response_body')->nullable()->comment('レスポンスボディ');
            $table->unsignedInteger('status_code')->comment('ステータスコード');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('sys_player_id');
            $table->index('system_at');
        });

        // ========================================
        // log_player: プレイヤー変更ログ
        // ========================================
        Schema::connection('log')->create('log_player', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->unique()->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('before_level')->comment('変更前レベル');
            $table->unsignedBigInteger('before_level_exp')->comment('変更前レベル経験値');
            $table->unsignedBigInteger('after_level')->comment('変更後レベル');
            $table->unsignedBigInteger('after_level_exp')->comment('変更後レベル経験値');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('sys_player_id');
            $table->index('system_at');
        });

        // ========================================
        // log_item: アイテム変更ログ
        // ========================================
        Schema::connection('log')->create('log_item', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->unique()->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_item_id')->comment('アイテムマスターID');
            $table->unsignedBigInteger('before_amount')->comment('変更前数量');
            $table->unsignedBigInteger('after_amount')->comment('変更後数量');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
            
            $table->index('sys_player_id');
            $table->index('mst_item_id');
            $table->index('system_at');
        });

        // ========================================
        // log_gacha: ガチャログ
        // ========================================
        Schema::connection('log')->create('log_gacha', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->unique()->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('mst_gacha_id')->comment('ガチャマスターID');
            $table->json('result')->nullable()->comment('ガチャ結果');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
            
            $table->index('sys_player_id');
            $table->index('mst_gacha_id');
            $table->index('system_at');
        });

        // ========================================
        // log_unit: ユニット変更ログ
        // ========================================
        Schema::connection('log')->create('log_unit', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->unique()->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('trx_unit_id')->comment('trx_unitテーブルのID');
            $table->string('mst_unit_id')->comment('ユニットマスターID');
            $table->unsignedBigInteger('before_grade')->comment('変更前グレード');
            $table->unsignedBigInteger('after_grade')->comment('変更後グレード');
            $table->unsignedBigInteger('before_level')->comment('変更前レベル');
            $table->unsignedBigInteger('before_level_exp')->default(0)->comment('変更前レベル経験値');
            $table->unsignedBigInteger('after_level')->comment('変更後レベル');
            $table->unsignedBigInteger('after_level_exp')->default(0)->comment('変更後レベル経験値');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
            
            $table->index('sys_player_id');
            $table->index('trx_unit_id');
            $table->index('mst_unit_id');
            $table->index('system_at');
        });

        // ========================================
        // log_equipment: 装備変更ログ
        // ========================================
        Schema::connection('log')->create('log_equipment', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->unique()->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('trx_equipment_id')->comment('trx_equipmentテーブルのID');
            $table->string('mst_equipment_id')->comment('装備マスターID');
            $table->unsignedBigInteger('before_grade')->comment('変更前グレード');
            $table->unsignedBigInteger('after_grade')->comment('変更後グレード');
            $table->unsignedBigInteger('before_level')->comment('変更前レベル');
            $table->unsignedBigInteger('before_level_exp')->comment('変更前レベル経験値');
            $table->unsignedBigInteger('after_level')->comment('変更後レベル');
            $table->unsignedBigInteger('after_level_exp')->comment('変更後レベル経験値');
            $table->dateTime('system_at')->comment('システム日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');
            
            $table->index('sys_player_id');
            $table->index('trx_equipment_id');
            $table->index('mst_equipment_id');
            $table->index('system_at');
        });

        // ========================================
        // log_in_app_purchase: アプリ内課金ログ
        // ========================================
        Schema::connection('log')->create('log_in_app_purchase', function (Blueprint $table) {
            $table->id()->comment('ログID');
            $table->string('unique_request_id')->unique()->comment('リクエスト一意ID (log_accessと結合)');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->enum('platform', ['apple', 'google'])->comment('プラットフォーム');
            $table->string('billing_platform')->comment('課金プラットフォーム (AppStore, GooglePlay, Stripe...)');
            $table->string('receipt_id')->comment('レシートID');
            $table->json('receipt')->comment('レシート情報');
            $table->string('status')->comment('ステータス (CheckAvailability, Purchased...)');
            $table->string('mst_in_app_purchase_id')->comment('アプリ内課金マスターID');
            $table->string('currency_code', 3)->comment('通貨コード (JPY, USD...)');
            $table->decimal('pay_amount', 10, 2)->comment('支払い金額');
            $table->string('pay_string')->comment('支払い金額表示文字列 ($9.2, ¥1980...)');
            $table->dateTime('system_at')->comment('APIの日時');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('sys_player_id');
            $table->index('platform');
            $table->index('status');
            $table->index('system_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('log')->dropIfExists('log_equipment');
        Schema::connection('log')->dropIfExists('log_in_app_purchase');
        Schema::connection('log')->dropIfExists('log_unit');
        Schema::connection('log')->dropIfExists('log_gacha');
        Schema::connection('log')->dropIfExists('log_item');
        Schema::connection('log')->dropIfExists('log_player');
        Schema::connection('log')->dropIfExists('log_access');
    }
};
