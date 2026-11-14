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
        // mst_config: 設定マスター（キーと値の 1 行 1 設定）
        // ========================================
        // 値は文字列の value に入れ、型は value_type で持つ（読む側が型で解釈する）。
        // 設定の追加は行の追加だけで済み、マイグレーションが要らない。
        // mst_* は全行がクライアントに配信される。秘密情報（鍵・内部 URL など）は入れない。
        // id は人が決める安定した文字列（AUTO_INCREMENT は使わない）。
        Schema::create('mst_config', function (Blueprint $table) {
            $table->integer('deploy_key')->default(202601010)->comment('デプロイキー');
            $table->string('id')->primary()->comment('設定ID（スネークケースの安定した文字列。例 stamina_recovery_interval_seconds）');
            $table->string('value')->comment('設定値（文字列。value_type で解釈する）');
            $table->enum('value_type', ['int', 'float', 'bool', 'string'])->comment('値の型 (int=整数, float=小数, bool=true/false, string=文字列)');
            $table->string('description')->comment('設定の説明（運用者向け。何に使い、どの単位か）');
            $table->dateTime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('作成日時');
            $table->dateTime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新日時');

            $table->index('deploy_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_config');
    }
};
