<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * VIPログインボーナス機能のマスターテーブルを作成
     *
     * mst_vip_login_bonus: VIPレベル別のログインボーナス設定
     * mst_vip_login_bonus_content: VIPログインボーナスの日別報酬内容
     */
    public function up(): void
    {
        // VIPログインボーナス設定テーブル
        Schema::connection('mst')->create('mst_vip_login_bonus', function (Blueprint $table) {
            $table->string('id', 64)->primary()->comment('VIPログインボーナスID（例: vip_login_lv5）');
            $table->unsignedTinyInteger('vip_level')->comment('対象VIPレベル');
            $table->unsignedInteger('loop_days')->comment('ループ日数（この日数で1日目に戻る）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->timestamp('start_at')->nullable()->comment('開始日時（UTC）');
            $table->timestamp('end_at')->nullable()->comment('終了日時（UTC）');
            $table->timestamps();

            // VIPレベルでの検索用インデックス
            $table->index('vip_level');
            $table->index(['is_active', 'start_at', 'end_at'], 'idx_active_period');
        });

        // VIPログインボーナスコンテンツテーブル
        Schema::connection('mst')->create('mst_vip_login_bonus_content', function (Blueprint $table) {
            $table->id();
            $table->string('mst_vip_login_bonus_id', 64)->comment('VIPログインボーナスID');
            $table->unsignedInteger('day')->comment('ログイン日数（1日目、2日目...）');
            $table->string('content_type', 32)->comment('報酬タイプ（diamond, item, unit等）');
            $table->string('content_mst_id', 64)->comment('報酬ID');
            $table->json('content_option')->nullable()->comment('報酬オプション（JSON）');
            $table->unsignedInteger('content_quantity')->default(1)->comment('報酬の基本個数');
            $table->unsignedInteger('amount')->default(1)->comment('報酬の倍率（実際の配布量 = content_quantity × amount）');
            $table->timestamps();

            // 外部キー制約
            $table->foreign('mst_vip_login_bonus_id', 'fk_vip_login_bonus_content_bonus_id')
                ->references('id')
                ->on('mst_vip_login_bonus')
                ->onDelete('cascade');

            // VIPログインボーナスID + 日数での検索用インデックス
            $table->index(['mst_vip_login_bonus_id', 'day'], 'idx_vip_login_bonus_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->dropIfExists('mst_vip_login_bonus_content');
        Schema::connection('mst')->dropIfExists('mst_vip_login_bonus');
    }
};
