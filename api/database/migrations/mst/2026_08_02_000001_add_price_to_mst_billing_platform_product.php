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
        Schema::connection('mst')->table('mst_billing_platform_product', function (Blueprint $table) {
            // 価格（マイクロ単位、1,000,000 = 1.00 USD/JPY等）
            $table->unsignedBigInteger('price_amount_micros')
                ->nullable()
                ->after('product_type')
                ->comment('価格（マイクロ単位、例: 1,000,000 = 1.00 USD）');
            
            // 通貨コード（ISO 4217）
            $table->string('price_currency_code', 3)
                ->nullable()
                ->after('price_amount_micros')
                ->comment('通貨コード（ISO 4217、例: USD, JPY）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->table('mst_billing_platform_product', function (Blueprint $table) {
            $table->dropColumn(['price_amount_micros', 'price_currency_code']);
        });
    }
};
