<?php

namespace App\Domain\Stamina\Contracts;

/**
 * StaminaInitializerInterface
 *
 * 他のコンテキストがスタミナの初期行を作るための公開 API。
 */
interface StaminaInitializerInterface
{
    /**
     * 通常スタミナ（type = normal）の初期行を作る
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $initialStamina  初期スタミナ量
     */
    public function initializeNormalStamina(int $sysPlayerId, int $initialStamina): void;
}
