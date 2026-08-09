<?php

namespace App\Exceptions;

use Exception;

/**
 * GameException
 *
 * ゲームAPIのビジネスロジックエラーを表すカスタム例外クラス
 * HTTPステータスコード299とerror_code, messageをレスポンスとして返す
 *
 * HTTP 299を使用する理由:
 * - 2xx範囲なのでネットワーク/プロキシレベルではエラーとして扱われない
 * - HTTP 200（完全な成功）と明確に区別できる
 * - クライアント側で if (status === 299) { handleBusinessError() } のように分岐可能
 * - レスポンスボディのerror_codeでエラー種別を詳細に特定
 */
class GameException extends Exception
{
    /**
     * @param  int  $errorCode  アプリケーション固有のエラーコード
     * @param  string  $message  エラーメッセージ
     */
    public function __construct(
        private readonly int $errorCode,
        string $message
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * エラーコードを取得
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
