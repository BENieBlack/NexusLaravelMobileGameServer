<?php

namespace NexusSecurity\Contracts;

/**
 * TokenValidatorInterface
 * 
 * アクセストークンの検証を行うインターフェース
 * アプリケーション側で実装する必要があります
 */
interface TokenValidatorInterface
{
    /**
     * アクセストークンを検証する
     *
     * @param string $token アクセストークン
     * @return array|null ペイロード（player_id, uuid等）、無効な場合はnull
     * @return array<string, mixed>|null
     */
    public function validateAccessToken(string $token): ?array;
}
