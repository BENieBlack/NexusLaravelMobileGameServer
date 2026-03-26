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
        // ========================================
        // sys_player: プレイヤー基本情報
        // ========================================
        Schema::connection('sys')->create('sys_player', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique()->comment('システム内部識別子（UUIDv4）');
            $table->string('my_id', 8)->unique()->comment('プレイヤーID（8桁英数、フレンド検索・問い合わせ両用）');
            $table->string('name', 100)->nullable()->comment('プレイヤー名（後で設定可能）');
            $table->unsignedInteger('level')->default(1)->comment('プレイヤーレベル');
            $table->unsignedBigInteger('level_exp')->default(0)->comment('レベル経験値（累積）');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('uuid');
            $table->index('my_id');
            $table->index('level');
        });

        // ========================================
        // sys_player_device: デバイス情報
        // ========================================
        Schema::connection('sys')->create('sys_player_device', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('uuid', 255)->unique()->comment('デバイス固有UUID（OSから取得）');
            $table->json('device_info')->nullable()->comment('デバイス情報 (os, os_version, model, app_version)');
            $table->dateTime('last_login_at')->nullable()->comment('最終ログイン日時');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('sys_player_id');
            $table->index('uuid');
        });

        // ========================================
        // sys_player_token: リフレッシュトークン管理
        // ========================================
        Schema::connection('sys')->create('sys_player_token', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('sys_player_device_id')->comment('sys_player_deviceテーブルのID');
            $table->string('refresh_token_hash', 64)->unique()->comment('リフレッシュトークンのSHA-256ハッシュ');
            $table->dateTime('expires_at')->comment('有効期限（30日）');
            $table->dateTime('revoked_at')->nullable()->comment('無効化日時');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('sys_player_id');
            $table->index('sys_player_device_id');
            $table->index('refresh_token_hash');
            $table->index('expires_at');
        });

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
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

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
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

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
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('deploy_key');
            $table->index('start_at');
            $table->index('sys_deploy_master_id');
            $table->index('sys_deploy_asset_id');
            $table->index('is_active');
            $table->index(['is_active', 'start_at']);
        });

        // ========================================
        // sys_sharding: シャーディング設定
        // ========================================
        Schema::connection('sys')->create('sys_sharding', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('シャーディング設定名（例: trx_sharding）');
            $table->string('target')->comment('シャーディング対象（例: transaction）');
            $table->enum('strategy', ['hash', 'range', 'consistent'])
                ->default('hash')
                ->comment('シャーディング方式');
            $table->string('sharding_key')->comment('シャーディングキー（例: player_id）');
            $table->unsignedInteger('node_count')->comment('ノード数');
            $table->boolean('is_active')->default(true)->comment('アクティブ状態');
            $table->text('description')->nullable()->comment('説明');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('target');
            $table->index('is_active');
        });

        // ========================================
        // sys_sharding_node: シャーディングノード
        // ========================================
        Schema::connection('sys')->create('sys_sharding_node', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sys_sharding_id')->comment('シャーディング設定ID');
            $table->string('node_name', 50)->comment('ノード名（例: node1, node2）');
            $table->unsignedTinyInteger('node_no')->comment('ノード番号（trx{node_no}で接続名を構築）');
            $table->integer('weight')->default(100)->comment('負荷分散用の重み（大きいほど優先）');
            $table->enum('status', ['active', 'inactive', 'maintenance'])
                ->default('active')
                ->comment('ノードステータス');
            $table->boolean('is_writable')->default(true)->comment('書き込み可能かどうか');
            $table->boolean('is_readable')->default(true)->comment('読み込み可能かどうか');
            $table->integer('max_connections')->default(100)->comment('最大同時接続数');
            $table->integer('current_player_count')->default(0)->comment('現在割り当てられているプレイヤー数');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('sys_sharding_id');
            $table->index('status');
            $table->unique(['sys_sharding_id', 'node_name'], 'uk_sharding_node_name');
            $table->unique(['sys_sharding_id', 'node_no'], 'uk_sharding_node_no');
        });

        // ========================================
        // sys_sharding_node_player: プレイヤーとノードの紐付け
        // ========================================
        Schema::connection('sys')->create('sys_sharding_node_player', function (Blueprint $table) {
            $table->unsignedBigInteger('sys_player_id')->primary()->comment('sys_playerテーブルのID');
            $table->unsignedBigInteger('sys_sharding_node_id')->comment('割り当てられたシャーディングノードID');
            $table->dateTime('assigned_at')->useCurrent()->comment('割り当て日時');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス（外部キー制約は使用しない）
            $table->index('sys_sharding_node_id');
            $table->index('assigned_at');
        });

        // ========================================
        // sys_friend_apply: フレンド申請
        // ========================================
        Schema::connection('sys')->create('sys_friend_apply', function (Blueprint $table) {
            $table->id()->comment('フレンド申請ID');
            $table->unsignedBigInteger('sender_sys_player_id')->comment('申請送信者のプレイヤーID');
            $table->unsignedBigInteger('receiver_sys_player_id')->comment('申請受信者のプレイヤーID');
            $table->enum('status', ['Applied', 'Accepted', 'Deleted'])
                ->default('Applied')
                ->comment('ステータス');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス（外部キー制約は使用しない）
            $table->index('sender_sys_player_id');
            $table->index('receiver_sys_player_id');
            $table->index('status');
            $table->index(['receiver_sys_player_id', 'status']);
        });

        // ========================================
        // sys_maintenance: メンテナンス管理
        // ========================================
        Schema::connection('sys')->create('sys_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->comment('メンテナンスタイトル');
            $table->text('message')->comment('メンテナンスメッセージ');
            $table->dateTime('start_at')->comment('メンテナンス開始日時');
            $table->dateTime('end_at')->nullable()->comment('メンテナンス終了日時');
            $table->boolean('is_active')->default(true)->comment('有効フラグ');
            $table->dateTime('created_at')->nullable()->comment('作成日時');
            $table->dateTime('updated_at')->nullable()->comment('更新日時');

            // インデックス
            $table->index('start_at');
            $table->index('end_at');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order
        Schema::connection('sys')->dropIfExists('sys_maintenance');
        Schema::connection('sys')->dropIfExists('sys_friend_apply');
        Schema::connection('sys')->dropIfExists('sys_sharding_node_player');
        Schema::connection('sys')->dropIfExists('sys_sharding_node');
        Schema::connection('sys')->dropIfExists('sys_sharding');
        Schema::connection('sys')->dropIfExists('sys_deploy');
        Schema::connection('sys')->dropIfExists('sys_deploy_asset');
        Schema::connection('sys')->dropIfExists('sys_deploy_master');
        Schema::connection('sys')->dropIfExists('sys_player_token');
        Schema::connection('sys')->dropIfExists('sys_player_device');
        Schema::connection('sys')->dropIfExists('sys_player');
    }
};
