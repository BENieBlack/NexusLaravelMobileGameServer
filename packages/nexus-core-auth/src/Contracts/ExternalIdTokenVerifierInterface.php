<?php

namespace NexusAuth\Contracts;

use NexusAuth\Exceptions\ExternalIdTokenException;

/**
 * ExternalIdTokenVerifierInterface
 *
 * 外部アカウント（Apple / Google など）が発行したIDトークンを検証する
 */
interface ExternalIdTokenVerifierInterface
{
    /**
     * IDトークンを検証し、外部アカウント側のユーザーID（sub）を返す
     *
     * 署名・有効期限・発行者（iss）・発行先（aud）を確かめる
     *
     * @param  string  $provider  外部アカウントの種類（apple, google）
     * @param  string  $idToken  クライアントがサインインで受け取ったIDトークン
     *
     * @throws ExternalIdTokenException 未対応・未設定の種類、または検証に失敗した場合
     */
    public function verify(string $provider, string $idToken): string;
}
