<?php

namespace App\Domain\Auth\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Auth\Responses\IssueTransferCodeResponse;
use App\Persistence\ApiSession;
use App\Repositories\Sys\SysPlayerTransferCodeRepository;
use RuntimeException;

/**
 * IssueTransferCodeUseCase
 *
 * 旧端末で引き継ぎコードを発行する。発行し直すと前のコードは使えなくなる
 */
class IssueTransferCodeUseCase extends _BaseUseCase
{
    /**
     * 読み間違えやすい文字（0/O, 1/I/L）を除いた英大文字と数字
     */
    private const CODE_CHARACTERS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 12;

    /**
     * コードが既存と重なったときに作り直す回数の上限
     */
    private const MAX_GENERATE_ATTEMPTS = 5;

    public function __construct(
        private readonly SysPlayerTransferCodeRepository $sysPlayerTransferCodeRepository,
    ) {}

    /**
     * @param  string  $password  新端末で入力させるパスワード（ハッシュにして保存する）
     */
    public function handle(int $sysPlayerId, string $password): IssueTransferCodeResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $password) {
            $expiresAt = ApiSession::getNow()
                ->addDays((int) config('account_transfer.transfer_code.expire_days'))
                ->format('Y-m-d H:i:s');

            $sysPlayerTransferCode = $this->sysPlayerTransferCodeRepository->upsertTransferCode(
                sysPlayerId: $sysPlayerId,
                transferCode: $this->generateUniqueCode(),
                passwordHash: password_hash($password, PASSWORD_DEFAULT),
                expiresAt: $expiresAt,
            );

            return new IssueTransferCodeResponse(
                transferCode: $sysPlayerTransferCode->getTransferCode(),
                expiresAt: $sysPlayerTransferCode->getExpiresAt(),
            );
        });
    }

    /**
     * 使われていない引き継ぎコードを作る
     */
    private function generateUniqueCode(): string
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATE_ATTEMPTS; $attempt++) {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_CHARACTERS[random_int(0, strlen(self::CODE_CHARACTERS) - 1)];
            }

            if (! $this->sysPlayerTransferCodeRepository->existsTransferCode($code)) {
                return $code;
            }
        }

        throw new RuntimeException('引き継ぎコードを生成できませんでした');
    }
}
