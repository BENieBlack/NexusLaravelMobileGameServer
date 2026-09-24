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
        // sys_deploy_master: マスターデータデプロイ
        // ========================================
        Schema::connection('sys')->create('sys_deploy_master', function (Blueprint $table) {
            $table->id();
            $table->integer('deploy_key')->unique()->comment('デプロイキー（YYYYMMDDN形式）');
            $table->string('hash', 64)->unique()->comment('マスターデータ全体のSHA-256ハッシュ（バージョン識別用）');
            $table->date('deploy_date')->comment('デプロイ日');
            $table->unsignedTinyInteger('deploy_count')->comment('その日の何回目のデプロイか');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'failed', 'rolled_back'])
                ->default('scheduled')
                ->comment('デプロイステータス');
            $table->string('deployed_by')->nullable()->comment('デプロイ実行者');
            $table->dateTime('deployed_at')->nullable()->comment('実際のデプロイ実行日時');
            $table->text('description')->nullable()->comment('デプロイ内容の説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('deploy_date');
            $table->index('hash');
            $table->index('status');
            $table->index(['deploy_date', 'deploy_count']);
        });

        // ========================================
        // sys_deploy_asset: アセットデプロイ
        // ========================================
        Schema::connection('sys')->create('sys_deploy_asset', function (Blueprint $table) {
            $table->id();
            $table->integer('deploy_key')->unique()->comment('デプロイキー（YYYYMMDDN形式）');
            $table->string('hash', 64)->unique()->comment('ファイル全体のSHA-256ハッシュ（バージョン識別用）');
            $table->date('deploy_date')->comment('デプロイ日');
            $table->unsignedTinyInteger('deploy_count')->comment('その日の何回目のデプロイか');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'failed', 'rolled_back'])
                ->default('scheduled')
                ->comment('デプロイステータス');
            $table->string('s3_bucket')->nullable()->comment('S3バケット名');
            $table->string('s3_path')->nullable()->comment('S3パス');
            $table->string('asset_version')->nullable()->comment('アセットバージョン');
            $table->bigInteger('total_size')->nullable()->comment('総アセットサイズ（バイト）');
            $table->integer('file_count')->nullable()->comment('アセットファイル数');
            $table->string('deployed_by')->nullable()->comment('デプロイ実行者');
            $table->dateTime('deployed_at')->nullable()->comment('実際のデプロイ実行日時');
            $table->text('description')->nullable()->comment('デプロイ内容の説明');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('deploy_date');
            $table->index('hash');
            $table->index('status');
            $table->index(['deploy_date', 'deploy_count']);
            $table->index('asset_version');
        });

        // ========================================
        // sys_deploy: デプロイ統合テーブル
        // ========================================
        Schema::connection('sys')->create('sys_deploy', function (Blueprint $table) {
            $table->id()->comment('デプロイID');
            $table->integer('deploy_key')->unique()->comment('デプロイキー（配信バージョン識別用）');
            $table->dateTime('start_at')->comment('DL可能となる日時');
            $table->unsignedBigInteger('sys_deploy_master_id')->comment('DLできるsys_deploy_masterのID');
            $table->unsignedBigInteger('sys_deploy_asset_id')->comment('DLできるsys_deploy_assetのID');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('deploy_key');
            $table->index('start_at');
            $table->index('sys_deploy_master_id');
            $table->index('sys_deploy_asset_id');
            $table->index('is_active');
            $table->index(['is_active', 'start_at']);
        });

        Schema::connection('sys')->create('sys_deploy_master_table', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_deploy_master_id')->comment('sys_deploy_masterテーブルのID');
            $table->string('table_name', 128)->comment('SQLiteに含まれるテーブルグループ名');
            $table->string('hash', 64)->comment('テーブルグループSQLiteのSHA-256ハッシュ');
            $table->unsignedBigInteger('file_size')->comment('SQLiteファイルサイズ（バイト）');
            $table->string('file_name', 255)->comment('ダウンロード対象ファイル名');
            $table->string('public_url', 512)->comment('ダウンロードURL');
            $table->primary(['sys_deploy_master_id', 'table_name'], 'pk_deploy_master_table');
            $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_deploy_master_table');
        Schema::connection('sys')->dropIfExists('sys_deploy');
        Schema::connection('sys')->dropIfExists('sys_deploy_asset');
        Schema::connection('sys')->dropIfExists('sys_deploy_master');
    }
};
