<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::connection('sys')->dropIfExists('sys_deploy_master_table');
    }
};
