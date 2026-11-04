<?php

namespace App\Http\Responses\Account;

use App\Http\Responses\Auth\SignInResponse;

/**
 * TransferByCodeResponse
 *
 * 引き継ぎコードによるデータ引き継ぎAPIのレスポンス。引き継ぎ後はサインインと同じ情報を返す（形は SignInResponse）
 */
class TransferByCodeResponse extends SignInResponse {}
