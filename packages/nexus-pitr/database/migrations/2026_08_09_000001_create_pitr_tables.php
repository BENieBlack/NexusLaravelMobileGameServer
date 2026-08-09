<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PITR用テーブル作成マイグレーション
 * 
 * 注意: このマイグレーションは`php artisan pitr:migrate`で実行してください。
 * PitrMigrateCommandが全LogDBシャード（log1, log2, ...）に対して自動的に実行します。
 * 
 * 直接実行する場合（非推奨）:
 *   php artisan migrate --database=log1 --path=database/migrations/log
 *   php artisan migrate --database=log2 --path=database/migrations/log
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 注意: Schema::connection()は使用しない
        // PitrMigrateCommandが--databaseオプションで接続を指定するため
        
        // ========================================
        // log_trx_change: TrxDB統合変更ログ (PITR用)
        // ========================================
        Schema::create('log_trx_change', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('ログID (UUID)');
            $table->string('unique_request_id', 100)->comment('リクエスト一意ID');
            $table->unsignedBigInteger('sys_player_id')->comment('sys_playerテーブルのID');
            $table->string('shard_connection', 20)->comment('シャード接続名 (trx1, trx2, ...)');
            $table->string('table_name', 100)->comment('対象テーブル名');
            $table->enum('operation', ['INSERT', 'UPDATE', 'DELETE'])->comment('操作種別');
            $table->json('before_data')->nullable()->comment('変更前データ (JSON)');
            $table->json('after_data')->nullable()->comment('変更後データ (JSON、UPDATEは差分のみ)');
            $table->json('primary_key')->comment('主キー (JSON)');
            $table->dateTime('system_at')->comment('システム日時');
            $table->string('api_endpoint', 255)->nullable()->comment('APIエンドポイント');
            $table->json('stack_trace')->nullable()->comment('スタックトレース (デバッグ用)');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('unique_request_id', 'idx_unique_request_id');
            $table->index('sys_player_id', 'idx_sys_player_id');
            $table->index(['shard_connection', 'table_name'], 'idx_shard_table');
            $table->index('system_at', 'idx_system_at');
            $table->index(['sys_player_id', 'system_at'], 'idx_player_time');
            $table->index(['shard_connection', 'system_at'], 'idx_shard_time');
        });

        // ========================================
        // log_pitr_recovery: PITR復旧履歴
        // ========================================
        Schema::create('log_pitr_recovery', function (Blueprint $table) {
            $table->id()->comment('復旧履歴ID');
            $table->string('recovery_id', 100)->unique()->comment('復旧実行ID (UUID)');
            $table->string('shard_connection', 20)->comment('対象シャード');
            $table->unsignedBigInteger('sys_player_id')->nullable()->comment('対象プレイヤーID (部分復旧時)');
            $table->string('table_name', 100)->nullable()->comment('対象テーブル (部分復旧時)');
            $table->dateTime('snapshot_time')->comment('スナップショット時刻');
            $table->dateTime('target_time')->comment('復旧目標時刻');
            $table->unsignedInteger('total_operations')->default(0)->comment('適用した操作数');
            $table->unsignedInteger('insert_count')->default(0)->comment('INSERT操作数');
            $table->unsignedInteger('update_count')->default(0)->comment('UPDATE操作数');
            $table->unsignedInteger('delete_count')->default(0)->comment('DELETE操作数');
            $table->enum('status', ['running', 'completed', 'failed', 'dry_run'])->comment('復旧状態');
            $table->text('error_message')->nullable()->comment('エラーメッセージ');
            $table->dateTime('started_at')->comment('開始日時');
            $table->dateTime('completed_at')->nullable()->comment('完了日時');
            $table->json('options')->nullable()->comment('復旧オプション (JSON)');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('shard_connection', 'idx_shard');
            $table->index('started_at', 'idx_started_at');
            $table->index('status', 'idx_status');
        });

        // ========================================
        // log_pitr_verification: PITR整合性検証ログ
        // ========================================
        Schema::create('log_pitr_verification', function (Blueprint $table) {
            $table->id()->comment('検証ログID');
            $table->string('verification_id', 100)->comment('検証実行ID (UUID)');
            $table->string('shard_connection', 20)->comment('対象シャード');
            $table->dateTime('check_time')->comment('検証時刻');
            $table->unsignedInteger('total_records')->default(0)->comment('検証対象レコード数');
            $table->unsignedInteger('missing_logs')->default(0)->comment('ログ欠損数');
            $table->unsignedInteger('orphaned_logs')->default(0)->comment('孤立ログ数');
            $table->unsignedInteger('inconsistent_data')->default(0)->comment('データ不整合数');
            $table->enum('status', ['passed', 'warning', 'failed'])->comment('検証結果');
            $table->json('details')->nullable()->comment('検証詳細 (JSON)');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            // インデックス
            $table->index('verification_id', 'idx_verification_id');
            $table->index('shard_connection', 'idx_shard');
            $table->index('check_time', 'idx_check_time');
            $table->index('status', 'idx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_pitr_verification');
        Schema::dropIfExists('log_pitr_recovery');
        Schema::dropIfExists('log_trx_change');
    }
};
