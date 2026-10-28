<?php

namespace NexusAgreement\Exceptions;

use RuntimeException;

/**
 * AgreementException
 *
 * 利用規約のドメイン例外
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、種類だけを持つ。
 * クライアントへ返すコードへの変換はアプリケーション層で行う。
 */
class AgreementException extends RuntimeException
{
    public const CODE_AGREEMENT_NOT_FOUND = 'AGREEMENT_NOT_FOUND';

    public const CODE_ALREADY_CONSENTED = 'ALREADY_CONSENTED';

    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 存在しない規約IDへの同意試行
     */
    public static function agreementNotFound(int $sysAgreementId): self
    {
        return new self(
            self::CODE_AGREEMENT_NOT_FOUND,
            "規約が見つかりません: sys_agreement_id={$sysAgreementId}",
        );
    }

    /**
     * 既に同意済みの規約への同意試行
     */
    public static function alreadyConsented(int $sysAgreementId): self
    {
        return new self(
            self::CODE_ALREADY_CONSENTED,
            "既に同意済みの規約です: sys_agreement_id={$sysAgreementId}",
        );
    }
}
