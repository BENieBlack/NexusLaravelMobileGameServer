<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * イベント基盤のマスターテーブル群
 *
 * mst_event            - イベント（期間の正本。start_at / end_at / exchange_end_at）
 * mst_event__l10n      - イベントの表示名・説明（多言語）
 * mst_event_content    - イベントに束ねるコンテンツ（ガチャ・ミッション・交換所など）
 */
return new class extends Migration
{
    protected $supportedLanguages = ['ja', 'en', 'zh-TW', 'zh-CN', 'hi', 'es', 'fr', 'ar', 'id', 'pt', 'bn', 'ru', 'de', 'ko'];

    public function up(): void
    {
        // ------------------------------------------------
        // mst_event
        // イベントの期間は全て重ならない（マスター取り込み時に検査する）。
        // 束ねたコンテンツの start_at / end_at は、このテーブルの期間で上書きされる
        // ------------------------------------------------
        Schema::create('mst_event', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('イベントID（例: event_202611_halloween）');
            $table->dateTime('start_at')->comment('開始日時');
            $table->dateTime('end_at')->comment('プレイ期間の終わり（ポイントの獲得・進捗の加算・ガチャなどの締め）');
            $table->dateTime('exchange_end_at')->comment('交換・受け取り期間の終わり（end_at以降。end_atと同じ値なら交換期間なし）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('start_at');
        });

        Schema::create('mst_event__l10n', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_event_id')->comment('mst_eventテーブルのID');
            $table->enum('language', $this->supportedLanguages)->comment('言語コード');
            $table->string('name')->comment('イベント名');
            $table->text('description')->nullable()->comment('イベントの説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_event_id', 'language'], 'pk_event_language');
            $table->index('deploy_key');
        });

        // ------------------------------------------------
        // mst_event_content
        // 同じコンテンツを複数のイベントに入れてよい（使い回し）。
        // 現在時刻に対して採用するイベントは EventPeriodResolver が決める
        // ------------------------------------------------
        Schema::create('mst_event_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_event_id')->comment('mst_eventテーブルのID');
            $table->string('content_type')->comment('コンテンツの種別 (gacha / login_bonus / reward_track / trade / mission / ranking)');
            $table->string('content_mst_id')->comment('コンテンツのマスターID（mst_gacha.id, mst_trade.id など）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->primary(['mst_event_id', 'content_type', 'content_mst_id'], 'pk_event_content');
            $table->index('deploy_key');
            $table->index(['content_type', 'content_mst_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mst_event_content');
        Schema::dropIfExists('mst_event__l10n');
        Schema::dropIfExists('mst_event');
    }
};
