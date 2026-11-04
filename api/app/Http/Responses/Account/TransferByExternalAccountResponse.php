<?php

namespace App\Http\Responses\Account;

use App\Http\Responses\Auth\SignInResponse;

/**
 * TransferByExternalAccountResponse
 *
 * 外部アカウントによるデータ引き継ぎAPIのレスポンス。引き継ぎ後はサインインと同じ情報を返す（形は SignInResponse）
 */
class TransferByExternalAccountResponse extends SignInResponse {}
