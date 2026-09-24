<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RewardTrack マスターテーブル群
 *
 * mst_reward_track          - トラック定義（期間・進捗タイプ）
 * mst_reward_track_line     - ライン定義（無料/有料ラインを複数定義可）
 * mst_reward_track_milestone - マイルストーン定義（必要進捗値）
 * mst_reward_track_content  - マイルストーン報酬（ライン×コンテンツ）
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------
        // mst_reward_track
        // ------------------------------------------------
        Schema::connection('mst')->create('mst_reward_track', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('トラックID（例: track_season_1）');
            $table->string('progress_type')->comment('進捗タイプ (player_level / point / quest_count / login_days)');
            $table->dateTime('start_at')->comment('開始日時');
            $table->dateTime('end_at')->nullable()->comment('終了日時（NULLは無期限）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->unsignedInteger('sort_desc')->default(0)->comment('表示順序（降順）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('is_active');
            $table->index(['is_active', 'start_at', 'end_at'], 'idx_active_period');
        });

        // ------------------------------------------------
        // mst_reward_track_line
        // 無料ラインは各トラックに必ず1つ存在する（is_free=true）
        // 有料ラインは mst_in_app_purchase と紐づく
        // ------------------------------------------------
        Schema::connection('mst')->create('mst_reward_track_line', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('ラインID（例: track_s1_free, track_s1_gold）');
            $table->string('mst_reward_track_id')->comment('mst_reward_trackテーブルのID');
            $table->boolean('is_free')->default(false)->comment('無料ラインフラグ（各トラックに必ず1つ）');
            $table->unsignedBigInteger('mst_in_app_purchase_id')->nullable()->comment('有料ラインの購入商品ID（is_free=trueの場合はNULL）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序（無料ラインを先頭に）');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_reward_track_id');
            $table->index(['mst_reward_track_id', 'is_free'], 'idx_track_free');
            $table->index(['mst_reward_track_id', 'sort_order'], 'idx_track_sort');
            $table->index('is_active');
        });

        // ------------------------------------------------
        // mst_reward_track_milestone
        // required_progress に達したら報酬が解放される
        // ------------------------------------------------
        Schema::connection('mst')->create('mst_reward_track_milestone', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('マイルストーンID（例: track_s1_lv5）');
            $table->string('mst_reward_track_id')->comment('mst_reward_trackテーブルのID');
            $table->unsignedInteger('required_progress')->comment('解放に必要な進捗値（レベル・ポイント等）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
            $table->index('mst_reward_track_id');
            $table->index(['mst_reward_track_id', 'required_progress'], 'idx_track_progress');
            $table->index(['mst_reward_track_id', 'sort_order'], 'idx_track_milestone_sort');
            $table->index('is_active');
        });

        // ------------------------------------------------
        // mst_reward_track_content
        // マイルストーン × ライン の組み合わせで報酬を定義する
        // 同一マイルストーン・同一ラインに複数コンテンツを持てる
        // ------------------------------------------------
        Schema::connection('mst')->create('mst_reward_track_content', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('mst_reward_track_milestone_id')->comment('mst_reward_track_milestoneテーブルのID');
            $table->string('mst_reward_track_line_id')->comment('mst_reward_track_lineテーブルのID');
            $table->string('content_type')->comment('コンテンツタイプ (item / unit / equipment / diamond / wallet / stamina)');
            $table->string('content_mst_id')->comment('コンテンツマスターID（diamond/stamina/walletはダミー値）');
            $table->json('content_option')->nullable()->comment('オプション（例: {"grade":1,"level":5}）');
            $table->unsignedInteger('content_quantity')->default(1)->comment('1配布あたりの数量');
            $table->unsignedInteger('amount')->default(1)->comment('配布回数（実際の配布量 = content_quantity × amount）');
            $table->unsignedInteger('sort_order')->default(0)->comment('表示順序');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // マイルストーン × ライン × コンテンツの組み合わせで一意
            $table->primary(
                ['mst_reward_track_milestone_id', 'mst_reward_track_line_id', 'content_type', 'content_mst_id'],
                'pk_reward_track_content'
            );

            $table->index('deploy_key');
            $table->index('mst_reward_track_milestone_id');
            $table->index('mst_reward_track_line_id');
            $table->index(['mst_reward_track_milestone_id', 'mst_reward_track_line_id'], 'idx_milestone_line');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        // 依存関係の逆順で削除
        Schema::connection('mst')->dropIfExists('mst_reward_track_content');
        Schema::connection('mst')->dropIfExists('mst_reward_track_milestone');
        Schema::connection('mst')->dropIfExists('mst_reward_track_line');
        Schema::connection('mst')->dropIfExists('mst_reward_track');
    }
};
