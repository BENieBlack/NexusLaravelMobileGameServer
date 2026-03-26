<?php

namespace App\Exceptions;

use Exception;

/**
 * GameException
 * 
 * ゲームAPIのビジネスロジックエラーを表すカスタム例外クラス
 * HTTPステータスコード999とerror_code, messageをレスポンスとして返す
 */
class GameException extends Exception
{
    /**
     * @param int $errorCode アプリケーション固有のエラーコード
     * @param string $message エラーメッセージ
     */
    public function __construct(
        private readonly int $errorCode,
        string $message
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * エラーコードを取得
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * エラーレスポンス配列を取得
     *
     * @return array{error_code: int, message: string}
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];
    }
}
