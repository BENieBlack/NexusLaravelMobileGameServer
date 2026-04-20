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
            $table->unsignedInteger('day')->comment('ログイン日数（1〜Nなど）');
            $table->unsignedInteger('loop_days')->default(7)->comment('ループ日数（7日、30日など）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('day');
            $table->index('is_active');
        });

        // ========================================
        // mst_login_bonus_content: ログインボーナス報酬内容
        // ========================================
        Schema::connection('mst')->create('mst_login_bonus_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_login_bonus_id')->comment('ログインボーナスID');
            $table->enum('content_type', ['item', 'unit', 'equipment', 'diamond', 'wallet'])->comment('コンテンツタイプ');
            $table->string('content_id')->comment('コンテンツID (mst_item_id, mst_unit_id等)');
            $table->unsignedInteger('amount')->default(1)->comment('数量');
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
