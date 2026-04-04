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
        // mst_gacha: ガチャマスター
        // ========================================
        Schema::connection('mst')->create('mst_gacha', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('ガチャID');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('start_at')->nullable()->comment('開始日時（NULL=常時）');
            $table->dateTime('end_at')->nullable()->comment('終了日時（NULL=無期限）');
            $table->unsignedInteger('daily_limit')->default(0)->comment('1日の実行回数制限（0=無制限）');
            $table->boolean('has_step_up')->default(false)->comment('ステップアップガチャか');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('is_active');
            $table->index(['start_at', 'end_at']);
        });

        // ========================================
        // mst_gacha__l10n: ガチャ多言語
        // ========================================
        Schema::connection('mst')->create('mst_gacha__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_gacha_id')->comment('ガチャID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('title')->comment('ガチャタイトル');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_gacha_id', 'language'], 'pk_gacha_language');
            $table->index('deploy_key');
        });

        // ========================================
        // mst_gacha_cost: ガチャコストマスター
        // ガチャの実行回数（1連、10連など）ごとのコスト設定
        // ========================================
        Schema::connection('mst')->create('mst_gacha_cost', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('コストID');
            $table->string('mst_gacha_id')->comment('ガチャID');
            $table->unsignedInteger('draw_count')->comment('実行回数（1連、10連など）');
            $table->enum('cost_type', ['diamond', 'paid_diamond', 'item'])->comment('コストタイプ');
            $table->string('cost_id')->nullable()->comment('コストID（itemの場合はmst_item_id）');
            $table->unsignedInteger('cost_amount')->comment('コスト量');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_gacha_id');
            $table->index('is_active');
            $table->unique(['mst_gacha_id', 'draw_count', 'cost_type', 'cost_id'], 'uk_gacha_cost');
        });

        // ========================================
        // mst_gacha_rarity_rate: ガチャレアリティ排出率マスター
        // レアリティ別の排出率設定（1~5）
        // ========================================
        Schema::connection('mst')->create('mst_gacha_rarity_rate', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('レアリティ排出率ID');
            $table->string('mst_gacha_id')->comment('ガチャID');
            $table->unsignedTinyInteger('rarity')->comment('レアリティ（1~5、5が最高レア）');
            $table->unsignedInteger('rate')->comment('排出率（10000分率、例：500=5%）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->unique(['mst_gacha_id', 'rarity'], 'uk_gacha_rarity');
        });

        // ========================================
        // mst_gacha_prize: ガチャ景品マスター
        // レアリティごとの個別オブジェクト排出設定
        // ========================================
        Schema::connection('mst')->create('mst_gacha_prize', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('景品ID');
            $table->string('mst_gacha_id')->comment('ガチャID');
            $table->unsignedTinyInteger('rarity')->comment('レアリティ（1~5）');
            $table->enum('content_type', ['item', 'unit', 'equipment'])->comment('コンテンツタイプ');
            $table->string('content_id')->comment('コンテンツID');
            $table->unsignedInteger('amount')->default(1)->comment('獲得数量');
            $table->unsignedInteger('weight')->default(1)->comment('重み（同レアリティ内での排出率）');
            $table->boolean('is_pickup')->default(false)->comment('ピックアップ対象か');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index(['mst_gacha_id', 'rarity']);
            $table->index('is_active');
            $table->index('is_pickup');
        });

        // ========================================
        // mst_gacha_step: ガチャステップマスター
        // ステップアップガチャのステップごとの設定
        // ========================================
        Schema::connection('mst')->create('mst_gacha_step', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('ステップID');
            $table->string('mst_gacha_id')->comment('ガチャID');
            $table->unsignedInteger('step_number')->comment('ステップ番号（1, 2, 3...）');
            $table->unsignedInteger('draw_count')->default(10)->comment('実行回数（通常10連）');
            $table->enum('cost_type', ['diamond', 'paid_diamond', 'item'])->nullable()->comment('コストタイプ上書き（NULL=通常設定を使用）');
            $table->string('cost_id')->nullable()->comment('コストID上書き（itemの場合）');
            $table->unsignedInteger('cost_amount')->nullable()->comment('コスト量上書き（NULL=通常設定を使用）');
            $table->boolean('is_loop_start')->default(false)->comment('ループ開始ステップか（このステップ以降繰り返す）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_gacha_id');
            $table->unique(['mst_gacha_id', 'step_number'], 'uk_gacha_step');
        });

        // ========================================
        // mst_gacha_step_guaranteed: ガチャステップ確定景品マスター
        // ステップごとの確定景品設定（複数設定可能）
        // パターン1: SSRがN体確定 → guaranteed_count > 1
        // パターン2: UnitAとUnitBどちらか確定 → selection_type='random', 候補を別テーブルで定義
        // パターン3: UnitAとUnitBのどちらかを選択 → selection_type='choice', 候補を別テーブルで定義
        // ========================================
        Schema::connection('mst')->create('mst_gacha_step_guaranteed', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('確定景品ID');
            $table->string('mst_gacha_step_id')->comment('ステップID');
            $table->unsignedInteger('position')->comment('確定位置（1回目、2回目など、0=ランダム位置）');
            $table->unsignedInteger('guaranteed_count')->default(1)->comment('確定数量（SSRがN体確定など）');
            $table->enum('selection_type', ['none', 'random', 'choice'])->default('none')->comment('選択タイプ（none=通常抽選, random=候補からランダム, choice=ユーザー選択）');
            $table->unsignedTinyInteger('guaranteed_rarity')->nullable()->comment('確定レアリティ（NULL=レアリティ指定なし）');
            $table->enum('guaranteed_content_type', ['item', 'unit', 'equipment'])->nullable()->comment('確定コンテンツタイプ（NULL=任意）');
            $table->boolean('is_pickup_only')->default(false)->comment('ピックアップ限定か');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_gacha_step_id');
            $table->unique(['mst_gacha_step_id', 'position'], 'uk_step_guaranteed');
        });

        // ========================================
        // mst_gacha_step_guaranteed_candidate: ガチャステップ確定景品候補マスター
        // selection_type='random'または'choice'の場合の候補リスト
        // ========================================
        Schema::connection('mst')->create('mst_gacha_step_guaranteed_candidate', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('候補ID');
            $table->string('mst_gacha_step_guaranteed_id')->comment('確定景品ID');
            $table->enum('content_type', ['item', 'unit', 'equipment'])->comment('コンテンツタイプ');
            $table->string('content_id')->comment('コンテンツID');
            $table->unsignedInteger('amount')->default(1)->comment('獲得数量');
            $table->unsignedInteger('weight')->default(1)->comment('重み（selection_typeがrandomの場合の抽選率）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序（selection_typeがchoiceの場合）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_gacha_step_guaranteed_id', 'idx_step_guaranteed_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_gacha_step_guaranteed_candidate');
        Schema::connection('mst')->dropIfExists('mst_gacha_step_guaranteed');
        Schema::connection('mst')->dropIfExists('mst_gacha_step');
        Schema::connection('mst')->dropIfExists('mst_gacha_prize');
        Schema::connection('mst')->dropIfExists('mst_gacha_rarity_rate');
        Schema::connection('mst')->dropIfExists('mst_gacha_cost');
        Schema::connection('mst')->dropIfExists('mst_gacha__l10n');
        Schema::connection('mst')->dropIfExists('mst_gacha');
    }
};
