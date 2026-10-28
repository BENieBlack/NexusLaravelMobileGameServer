<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * タグのマスターテーブル群
 *
 * mst_tag          - タグ定義（作品・種別・属性など、分類は tag_category）
 * mst_tag__l10n    - タグの表示名（多言語）
 * mst_tag_content  - どのマスター（ユニット・アイテム等）にどのタグが付くか（多対多）
 *
 * 1つのマスターに複数のタグを付けられるため、「Vガンダム系」と「ガンダムタイプ」の
 * 両方に属するユニットを、両方の条件で拾える。
 */
return new class extends Migration
{
    protected $supportedLanguages = ['ja', 'en', 'zh-TW', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'id', 'pt', 'bn', 'ru', 'de', 'ko'];

    public function up(): void
    {
        // ------------------------------------------------
        // mst_tag
        // ------------------------------------------------
        Schema::create('mst_tag', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('タグID（例: tag_series_v_gundam）');
            $table->string('tag_category')->comment('タグの分類 (series / chara_type / element など。ゲームごとに決める)');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('tag_category');
        });

        // ------------------------------------------------
        // mst_tag__l10n
        // ------------------------------------------------
        Schema::create('mst_tag__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_tag_id')->comment('mst_tagテーブルのID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('タグの表示名（例: Vガンダム）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_tag_id', 'language'], 'pk_tag_language');
            $table->index('deploy_key');
        });

        // ------------------------------------------------
        // mst_tag_content
        // タグとマスターの中間テーブル（多対多）
        // ------------------------------------------------
        Schema::create('mst_tag_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_tag_id')->comment('mst_tagテーブルのID');
            $table->string('content_type')->comment('タグを付ける対象の種別 (unit / equipment / item)');
            $table->string('content_mst_id')->comment('種別に対応するマスターのID（mst_unit.id など）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // 同じ対象に同じタグを二重に付けない
            $table->primary(['mst_tag_id', 'content_type', 'content_mst_id'], 'pk_tag_content');
            $table->index('deploy_key');
            // 「この対象のタグ一覧」を引くための索引（主キーは mst_tag_id が先頭で使えない）
            $table->index(['content_type', 'content_mst_id'], 'idx_tag_content_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_tag_content');
        Schema::dropIfExists('mst_tag__l10n');
        Schema::dropIfExists('mst_tag');
    }
};
