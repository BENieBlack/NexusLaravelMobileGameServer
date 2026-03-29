<?php

namespace App\Exceptions;

/**
 * HttpStatusCode
 * 
 * カスタムHTTPステータスコード定義（600番台）
 * 標準のHTTPステータスコード範囲（100-599）に重複しない独自エラーコード
 */
class HttpStatusCode
{
    /**
     * 600: アプリケーション/ビジネスロジックエラー（GameException）
     * 
     * レスポンスボディのerror_codeで詳細を区別
     * 例：アイテム不足、レベル上限、重複リクエストなど
     */
    const GAME_ERROR = 600;

    /**
     * 601: メンテナンス中
     * 
     * システムメンテナンス、緊急メンテナンス
     */
    const MAINTENANCE = 601;

    /**
     * 602: 強制アップデート必要
     * 
     * クライアントのアプリバージョンが古すぎて利用不可
     * アプリストアへの誘導が必要
     */
    const FORCE_UPDATE = 602;

    /**
     * 603: アカウント利用停止
     * 
     * BAN、アカウント凍結、利用規約違反など
     * プレイヤーアカウントが無効化されている
     */
    const ACCOUNT_SUSPENDED = 603;

    /**
     * 604: サーバー過負荷/同時接続制限
     * 
     * サーバーが満杯、しばらく待ってリトライが必要
     * 503とは異なり、ゲーム固有の制限
     */
    const SERVER_OVERLOAD = 604;

    /**
     * 605: 不正検知
     * 
     * チート検知、改造アプリ検知など
     * セキュリティ上の理由でアクセス拒否
     */
    const FRAUD_DETECTED = 605;
}
