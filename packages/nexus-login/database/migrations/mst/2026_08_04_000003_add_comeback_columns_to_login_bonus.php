<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * カムバックログインボーナス機能のためにmst_login_bonusテーブルを拡張
     */
    public function up(): void
    {
        Schema::connection('mst')->table('mst_login_bonus', function (Blueprint $table) {
            // typeカラム: 'daily' (通常) or 'comeback' (カムバック)
            $table->enum('type', ['daily', 'comeback'])
                  ->default('daily')
                  ->comment('ログインボーナスタイプ')
                  ->after('id');
            
            // カムバックボーナス用: 必要休眠日数
            $table->unsignedInteger('required_absent_days')
                  ->nullable()
                  ->comment('必要休眠日数（カムバック用、nullの場合は通常）')
                  ->after('loop_days');
            
            // カムバックボーナス用: 有効期間
            $table->unsignedInteger('valid_days')
                  ->nullable()
                  ->comment('ボーナス有効期間（カムバック用）')
                  ->after('required_absent_days');
            
            // カムバックボーナス用: 優先度
            $table->unsignedInteger('priority')
                  ->default(0)
                  ->comment('優先度（カムバック用、複数条件該当時の優先順位）')
                  ->after('valid_days');
            
            // 期間限定ボーナス用: 開始日時
            $table->dateTime('start_at')
                  ->nullable()
                  ->comment('開始日時（期間限定用）')
                  ->after('is_active');
            
            // 期間限定ボーナス用: 終了日時
            $table->dateTime('end_at')
                  ->nullable()
                  ->comment('終了日時（期間限定用）')
                  ->after('start_at');
            
            // インデックス追加
            $table->index('type', 'idx_type');
            $table->index('required_absent_days', 'idx_required_absent_days');
            $table->index('priority', 'idx_priority');
            $table->index(['type', 'is_active'], 'idx_type_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mst')->table('mst_login_bonus', function (Blueprint $table) {
            $table->dropIndex('idx_type');
            $table->dropIndex('idx_required_absent_days');
            $table->dropIndex('idx_priority');
            $table->dropIndex('idx_type_active');
            
            $table->dropColumn([
                'type',
                'required_absent_days',
                'valid_days',
                'priority',
                'start_at',
                'end_at',
            ]);
        });
    }
};
