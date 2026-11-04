<?php

namespace App\Domain\Auth\Responses;

/**
 * TransferByExternalAccountResponse
 *
 * 外部アカウントによるデータ引き継ぎAPIのレスポンス。引き継ぎ後はサインインと同じ情報を返す（形は SignInResponse）
 */
class TransferByExternalAccountResponse extends SignInResponse {}
