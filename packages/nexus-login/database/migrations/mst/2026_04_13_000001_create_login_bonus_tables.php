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
        // mst_login_bonus: ログインボーナス設定マスター
        // ========================================
        Schema::connection('mst')->create('mst_login_bonus', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('ログインボーナスID');
            $table->enum('type', ['daily', 'comeback'])
                  ->default('daily')
                  ->comment('ログインボーナスタイプ');
            $table->unsignedInteger('day')->comment('ログイン日数（1〜Nなど）');
            $table->unsignedInteger('loop_days')->default(7)->comment('ループ日数（7日、30日など）');
            $table->unsignedInteger('required_absent_days')
                  ->nullable()
                  ->comment('必要休眠日数（カムバック用、nullの場合は通常）');
            $table->unsignedInteger('valid_days')
                  ->nullable()
                  ->comment('ボーナス有効期間（カムバック用）');
            $table->unsignedInteger('priority')
                  ->default(0)
                  ->comment('優先度（カムバック用、複数条件該当時の優先順位）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('start_at')
                  ->nullable()
                  ->comment('開始日時（期間限定用）');
            $table->dateTime('end_at')
                  ->nullable()
                  ->comment('終了日時（期間限定用）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('day');
            $table->index('is_active');
            $table->index('type', 'idx_type');
            $table->index('required_absent_days', 'idx_required_absent_days');
            $table->index('priority', 'idx_priority');
            $table->index(['type', 'is_active'], 'idx_type_active');
        });

        // ========================================
        // mst_login_bonus_content: ログインボーナス報酬内容
        // ========================================
        Schema::connection('mst')->create('mst_login_bonus_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_login_bonus_id')->comment('ログインボーナスID');
            $table->enum('content_type', ['item', 'unit', 'equipment', 'diamond', 'wallet'])->comment('コンテンツタイプ');
            $table->string('content_id')->comment('コンテンツID (mst_item_id, mst_unit_id等)');
            $table->json('content_option')->nullable()->comment('コンテンツオプション (例: {"grade":1, "level":5})');
            $table->unsignedInteger('content_quantity')->default(1)->comment('1配布あたりのコンテンツ数量');
            $table->unsignedInteger('amount')->default(1)->comment('配布回数（content_quantity × amount = 実際の配布量）');
            $table->boolean('is_paid')->default(false)->comment('有償フラグ（wallet/diamondの場合）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_login_bonus_id', 'content_type', 'content_id'], 'pk_login_bonus_content');
            $table->index('deploy_key');
            $table->index('sort_order');
            
            // 外部キー制約
            $table->foreign('mst_login_bonus_id', 'fk_login_bonus_content')
                  ->references('id')
                  ->on('mst_login_bonus')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_login_bonus_content');
        Schema::connection('mst')->dropIfExists('mst_login_bonus');
    }
};
