<?php

namespace App\Http\Controllers;

use App\Domain\Auth\UseCases\ExternalAccountListUseCase;
use App\Domain\Auth\UseCases\IssueTransferCodeUseCase;
use App\Domain\Auth\UseCases\LinkExternalAccountUseCase;
use App\Domain\Auth\UseCases\TransferByCodeUseCase;
use App\Domain\Auth\UseCases\TransferByExternalAccountUseCase;
use App\Domain\Auth\UseCases\UnlinkExternalAccountUseCase;
use App\Http\Requests\Account\IssueTransferCodeRequest;
use App\Http\Requests\Account\LinkExternalAccountRequest;
use App\Http\Requests\Account\TransferByCodeRequest;
use App\Http\Requests\Account\TransferByExternalAccountRequest;
use App\Http\Requests\Account\UnlinkExternalAccountRequest;
use App\Http\Responses\Account\ExternalAccountListResponse;
use App\Http\Responses\Account\IssueTransferCodeResponse;
use App\Http\Responses\Account\LinkExternalAccountResponse;
use App\Http\Responses\Account\TransferByCodeResponse;
use App\Http\Responses\Account\TransferByExternalAccountResponse;
use App\Http\Responses\Account\UnlinkExternalAccountResponse;
use App\Persistence\ApiSession;
use Illuminate\Http\JsonResponse;

/**
 * AccountController
 *
 * データ引き継ぎ（機種変更）のAPIエンドポイント
 *
 * - 引き継ぎコード: 旧端末で発行し、新端末でコードとパスワードを入力する
 * - 外部アカウント: 旧端末で Apple / Google を紐付け、新端末で同じアカウントにサインインする
 *
 * 引き継ぎに成功すると sign_in と同じ形でトークンを返す。旧端末はサインインできなくなる。
 */
class AccountController extends _BaseController
{
    /**
     * 引き継ぎコードを発行（発行し直すと前のコードは使えなくなる）
     *
     * POST /api/account/transfer_code/issue
     */
    public function issueTransferCode(IssueTransferCodeRequest $request, IssueTransferCodeUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => IssueTransferCodeResponse::fromResult($useCase->handle(
            sysPlayerId: ApiSession::getSysPlayerId(),
            password: $request->getPassword(),
        )));
    }

    /**
     * 引き継ぎコードでアカウントを引き継ぐ（認証不要）
     *
     * POST /api/account/transfer_code/transfer
     */
    public function transferByCode(TransferByCodeRequest $request, TransferByCodeUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => TransferByCodeResponse::fromResult($useCase->handle(
            transferCode: $request->getTransferCode(),
            password: $request->getPassword(),
            deviceId: $request->getDeviceId(),
            deviceInfo: $request->getDeviceInfo(),
        )));
    }

    /**
     * 紐付いている外部アカウントの一覧
     *
     * GET /api/account/external/list
     */
    public function externalAccountList(ExternalAccountListUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => ExternalAccountListResponse::fromResult(
            $useCase->handle(ApiSession::getSysPlayerId())
        ));
    }

    /**
     * 外部アカウントを紐付ける
     *
     * POST /api/account/external/link
     */
    public function linkExternalAccount(LinkExternalAccountRequest $request, LinkExternalAccountUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => LinkExternalAccountResponse::fromResult($useCase->handle(
            sysPlayerId: ApiSession::getSysPlayerId(),
            provider: $request->getProvider(),
            idToken: $request->getIdToken(),
        )));
    }

    /**
     * 外部アカウントの紐付けを外す
     *
     * POST /api/account/external/unlink
     */
    public function unlinkExternalAccount(UnlinkExternalAccountRequest $request, UnlinkExternalAccountUseCase $useCase): JsonResponse
    {
        return $this->execute(fn () => UnlinkExternalAccountResponse::fromResult($useCase->handle(
            sysPlayerId: ApiSession::getSysPlayerId(),
            provider: $request->getProvider(),
        )));
    }

    /**
     * 外部アカウントでアカウントを引き継ぐ（認証不要）
     *
     * POST /api/account/external/transfer
     */
    public function transferByExternalAccount(
        TransferByExternalAccountRequest $request,
        TransferByExternalAccountUseCase $useCase,
    ): JsonResponse {
        return $this->execute(fn () => TransferByExternalAccountResponse::fromResult($useCase->handle(
            provider: $request->getProvider(),
            idToken: $request->getIdToken(),
            deviceId: $request->getDeviceId(),
            deviceInfo: $request->getDeviceInfo(),
        )));
    }
}
