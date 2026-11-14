<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ミッションのマスターテーブル群
 *
 * mst_mission          - ミッション定義（何を数えるか・条件・目標値・期間）
 * mst_mission__l10n    - ミッションの表示名・説明（多言語）
 * mst_mission_content  - 達成報酬
 */
return new class extends Migration
{
    protected $supportedLanguages = ['ja', 'en', 'zh-TW', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'id', 'pt', 'bn', 'ru', 'de', 'ko'];

    public function up(): void
    {
        // ------------------------------------------------
        // mst_mission
        // trx_progress の (trigger_type, mst_tag_id) のカウンタが target_value に達したら達成。
        // 例: 「Vガンダム系を3体完凸」
        //   trigger_type = unit_max_limit_break, mst_tag_id = tag_series_v_gundam, target_value = 3
        // ------------------------------------------------
        Schema::create('mst_mission', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('ミッションID（例: mission_v_gundam_level_up_3）');
            $table->string('trigger_type')->comment('進捗を進める出来事 (unit_level_up / unit_max_limit_break など。ゲームごとに決める)');
            $table->string('mst_tag_id')->nullable()->comment('mst_tagテーブルのID。このタグを持つ対象の分だけ数える（NULLはタグを問わない）');
            $table->unsignedInteger('target_value')->comment('達成に必要な進捗値');
            $table->dateTime('start_at')->comment('表示・受け取りの開始日時（進捗は期間に関係なく通算で数える）');
            $table->dateTime('end_at')->nullable()->comment('表示・受け取りの終了日時（NULLは無期限）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('trigger_type');
            $table->index('mst_tag_id');
        });

        // ------------------------------------------------
        // mst_mission__l10n
        // ------------------------------------------------
        Schema::create('mst_mission__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_mission_id')->comment('mst_missionテーブルのID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('ミッション名');
            $table->text('description')->nullable()->comment('ミッションの説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_mission_id', 'language'], 'pk_mission_language');
            $table->index('deploy_key');
        });

        // ------------------------------------------------
        // mst_mission_content
        // 1つのミッションに複数の報酬を持てる
        // ------------------------------------------------
        Schema::create('mst_mission_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_mission_id')->comment('mst_missionテーブルのID');
            $table->string('content_type')->comment('報酬の種別 (item / unit / equipment / stamina など。無償ダイヤは item + free_diamond)');
            $table->string('content_mst_id')->comment('報酬のマスターID（staminaはダミー値。無償ダイヤは free_diamond）');
            $table->unsignedInteger('content_quantity')->default(1)->comment('配布する数量');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_mission_id', 'content_type', 'content_mst_id'], 'pk_mission_content');
            $table->index('deploy_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_mission_content');
        Schema::dropIfExists('mst_mission__l10n');
        Schema::dropIfExists('mst_mission');
    }
};
