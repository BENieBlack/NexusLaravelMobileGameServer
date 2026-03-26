<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ========================================
        // Phase 1: マスターデータのシード
        // ========================================
        $this->command->info('📦 Phase 1: Seeding Master Data...');
        $this->call([
            // 基本マスターデータ（依存関係なし、並列実行可能）
            MstUnitSeeder::class,
            MstItemSeeder::class,
            MstEquipmentSeeder::class,
            MstPlayerLevelSeeder::class,
            
            // 課金関連マスターデータ（MstBillingPlatformProductが先、MstInAppPurchaseが依存）
            MstBillingPlatformProductSeeder::class,
            MstInAppPurchaseSeeder::class,
        ]);

        // ========================================
        // Phase 2: システムデータのシード
        // ========================================
        $this->command->info('⚙️  Phase 2: Seeding System Data...');
        $this->call([
            // シャーディング設定（プレイヤー作成前に必要）
            SysShardingSeeder::class,
            
            // プレイヤー基本情報（全ての依存元）
            SysPlayerSeeder::class,
            
            // デプロイ管理（プレイヤーに依存しない）
            SysDeploySeeder::class,
            
            // フレンド申請（プレイヤーに依存）
            SysFriendApplySeeder::class,
        ]);

        // ========================================
        // Phase 3: トランザクションデータのシード
        // ========================================
        $this->command->info('💾 Phase 3: Seeding Transaction Data (Sharded)...');
        $this->call([
            // プレイヤートランザクションデータ（シャーディング対応）
            TrxPlayerSeeder::class,
            
            // プレイヤー所持ユニット（プレイヤーとマスターに依存）
            TrxUnitSeeder::class,
            
            // プレイヤー所持アイテム（プレイヤーとマスターに依存）
            TrxItemSeeder::class,
        ]);

        // ========================================
        // Phase 4: ログデータのシード（開発・テスト用）
        // ========================================
        if (app()->environment('local', 'development')) {
            $this->command->info('📊 Phase 4: Seeding Log Data (Development Only)...');
            $this->call([
                // アクセスログ（大量データ）
                LogAccessSeeder::class,
                
                // 課金ログ
                LogInAppPurchaseSeeder::class,
                
                // プレイヤー変更ログ
                LogPlayerSeeder::class,
                
                // アイテム変更ログ
                LogItemSeeder::class,
                
                // ユニット変更ログ
                LogUnitSeeder::class,
                
                // ガチャログ
                LogGachaSeeder::class,
            ]);
        }

        // ========================================
        // Seeding Complete
        // ========================================
        $this->command->info('');
        $this->command->info('🎉 All seeders completed successfully!');
    }
}
