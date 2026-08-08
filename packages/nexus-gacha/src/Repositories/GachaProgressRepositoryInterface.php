<?php

namespace NexusGacha\Repositories;

use NexusGacha\Dto\GachaProgressDto;

/**
 * GachaProgressRepositoryInterface
 * 
 * ガチャ進行状況データへのアクセスを抽象化
 */
interface GachaProgressRepositoryInterface
{
    /**
     * プレイヤーとガチャIDで進行状況を取得
     * 
     * @param int $sysPlayerId
     * @param string $mstGachaId
     * @return GachaProgressDto|null
     */
    public function findByPlayerAndGacha(int $sysPlayerId, string $mstGachaId): ?GachaProgressDto;

    /**
     * 進行状況を保存
     * 
     * @param GachaProgressDto $gachaProgressDto
     * @return void
     */
    public function save(GachaProgressDto $gachaProgressDto): void;

    /**
     * 新規進行状況を作成
     * 
     * @param GachaProgressDto $gachaProgressDto
     * @return GachaProgressDto
     */
    public function create(GachaProgressDto $gachaProgressDto): GachaProgressDto;
}
