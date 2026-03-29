<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Trx\TrxUnit;
use App\Models\Trx\TrxEquipment;
use App\Models\Trx\TrxDiamondBalance;
use App\Models\Trx\TrxStamina;
use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysFriendApply;
use App\Models\Mst\MstInAppPurchase;

/**
 * toResponseArray() メソッドのテスト
 * {table_name}_id形式への変換が正しく動作することを確認
 */
class ToResponseArrayTest extends TestCase
{
    /**
     * TrxUnit: id → trx_unit_id に変換されること
     */
    public function trx_unit_converts_id_to_trx_unit_id()
    {
        $unit = new TrxUnit();
        $unit->id = 123;
        $unit->sys_player_id = 1;
        $unit->mst_unit_id = 'unit_001';
        $unit->level = 5;
        $unit->level_exp = 100;
        
        $response = $unit->toResponseArray();
        
        $this->assertArrayHasKey('trx_unit_id', $response);
        $this->assertEquals(123, $response['trx_unit_id']);
        $this->assertArrayNotHasKey('id', $response);
    }

    /**
     * TrxEquipment: id → trx_equipment_id に変換されること
     */
    public function trx_equipment_converts_id_to_trx_equipment_id()
    {
        $equipment = new TrxEquipment();
        $equipment->id = 456;
        $equipment->sys_player_id = 1;
        $equipment->mst_equipment_id = 'equipment_sword_001';
        $equipment->level = 10;
        $equipment->level_exp = 500;
        
        $response = $equipment->toResponseArray();
        
        $this->assertArrayHasKey('trx_equipment_id', $response);
        $this->assertEquals(456, $response['trx_equipment_id']);
        $this->assertArrayNotHasKey('id', $response);
    }

    /**
     * TrxDiamondBalance: id → trx_diamond_balance_id に変換されること
     */
    public function trx_diamond_balance_converts_id_to_trx_diamond_balance_id()
    {
        $balance = new TrxDiamondBalance();
        $balance->id = 789;
        $balance->sys_player_id = 1;
        $balance->platform = 'apple';
        $balance->billing_platform = 'AppStore';
        $balance->current_amount = 1000;
        $balance->purchase_amount = 1000;
        $balance->unit_price = 0.99;
        
        $response = $balance->toResponseArray();
        
        $this->assertArrayHasKey('trx_diamond_balance_id', $response);
        $this->assertEquals(789, $response['trx_diamond_balance_id']);
        $this->assertArrayNotHasKey('id', $response);
    }

    /**
     * TrxStamina: id → trx_stamina_id に変換されること
     */
    public function trx_stamina_converts_id_to_trx_stamina_id()
    {
        $stamina = new TrxStamina();
        $stamina->id = 111;
        $stamina->sys_player_id = 1;
        $stamina->current_stamina = 50;
        $stamina->overflow_stamina = 0;
        $stamina->recovery_rate_multiplier = 1.0;
        
        $response = $stamina->toResponseArray();
        
        $this->assertArrayHasKey('trx_stamina_id', $response);
        $this->assertEquals(111, $response['trx_stamina_id']);
        $this->assertArrayNotHasKey('id', $response);
    }

    /**
     * SysPlayer: id → sys_player_id に変換されること
     */
    public function sys_player_converts_id_to_sys_player_id()
    {
        $player = new SysPlayer();
        $player->id = 222;
        $player->uuid = 'test-uuid-123';
        $player->my_id = 'MY123456';
        $player->name = 'Test Player';
        $player->level = 20;
        $player->level_exp = 5000;
        
        $response = $player->toResponseArray();
        
        $this->assertArrayHasKey('sys_player_id', $response);
        $this->assertEquals(222, $response['sys_player_id']);
        $this->assertArrayNotHasKey('id', $response);
    }

    /**
     * SysFriendApply: id → sys_friend_apply_id に変換されること
     */
    public function sys_friend_apply_converts_id_to_sys_friend_apply_id()
    {
        $friendApply = new SysFriendApply();
        $friendApply->id = 333;
        $friendApply->sender_sys_player_id = 1;
        $friendApply->receiver_sys_player_id = 2;
        $friendApply->status = 'pending';
        
        $response = $friendApply->toResponseArray();
        
        $this->assertArrayHasKey('sys_friend_apply_id', $response);
        $this->assertEquals(333, $response['sys_friend_apply_id']);
        $this->assertArrayNotHasKey('id', $response);
    }

    /**
     * MstInAppPurchase: id → mst_in_app_purchase_id に変換されること
     */
    public function mst_in_app_purchase_converts_id_to_mst_in_app_purchase_id()
    {
        $purchase = new MstInAppPurchase();
        $purchase->id = 444;
        $purchase->deploy_key = 202501011;
        $purchase->type = 'Diamond';
        $purchase->paid_diamond_amount = 100;
        $purchase->is_active = true;
        
        $response = $purchase->toResponseArray();
        
        $this->assertArrayHasKey('mst_in_app_purchase_id', $response);
        $this->assertEquals(444, $response['mst_in_app_purchase_id']);
        $this->assertArrayNotHasKey('id', $response);
    }
}
