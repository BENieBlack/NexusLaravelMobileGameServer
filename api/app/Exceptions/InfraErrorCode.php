<?php

namespace App\Exceptions;

/**
 * InfraErrorCode
 *
 * インフラストラクチャ層のエラーコード定義（3桁以下: 1-999）
 *
 * データベース、Redis、暗号化等のインフラレベルのエラーを定義
 * これらのエラーはフレームワーク層で発生し、アプリケーション層に依存しない
 *
 * エラーコード範囲:
 * - 100-199: データベースエラー
 * - 200-299: キャッシュ/Redisエラー
 * - 300-399: 暗号化/セキュリティエラー
 * - 400-499: データ整合性エラー
 * - 500-599: タイムアウトエラー
 * - 600-699: 設定/環境エラー
 * - 700-999: 汎用システムエラー
 */
class InfraErrorCode
{
    // ========================================
    // データベースエラー (100-199)
    // ========================================

    /**
     * データベース接続失敗
     */
    public const DB_CONNECTION_FAILED = 101;

    /**
     * データベースクエリエラー
     */
    public const DB_QUERY_ERROR = 102;

    /**
     * 重複エントリ（Duplicate Entry）
     */
    public const DUPLICATE_ENTRY = 103;

    /**
     * 外部キー制約違反
     */
    public const FOREIGN_KEY_CONSTRAINT = 104;

    /**
     * デッドロック検出
     */
    public const DEADLOCK_DETECTED = 105;

    /**
     * トランザクションロールバック
     */
    public const TRANSACTION_ROLLBACK = 106;

    // ========================================
    // キャッシュ/Redisエラー (200-299)
    // ========================================

    /**
     * Redis接続失敗
     */
    public const REDIS_CONNECTION_FAILED = 201;

    /**
     * Redis操作タイムアウト
     */
    public const REDIS_TIMEOUT = 202;

    /**
     * キャッシュ書き込み失敗
     */
    public const CACHE_WRITE_FAILED = 203;

    /**
     * キャッシュ読み込み失敗
     */
    public const CACHE_READ_FAILED = 204;

    /**
     * キャッシュクリア失敗
     */
    public const CACHE_CLEAR_FAILED = 205;

    // ========================================
    // 暗号化/セキュリティエラー (300-399)
    // ========================================

    /**
     * 暗号化失敗
     */
    public const ENCRYPTION_FAILED = 301;

    /**
     * 復号化失敗
     */
    public const DECRYPTION_FAILED = 302;

    /**
     * ハッシュ検証失敗
     */
    public const HASH_VERIFICATION_FAILED = 303;

    /**
     * トークン期限切れ
     */
    public const TOKEN_EXPIRED = 304;

    /**
     * 署名検証失敗
     */
    public const SIGNATURE_VERIFICATION_FAILED = 305;

    /**
     * 不正なトークンフォーマット
     */
    public const INVALID_TOKEN_FORMAT = 306;

    // ========================================
    // データ整合性エラー (400-499)
    // ========================================

    /**
     * マスタデータが見つからない
     */
    public const MASTER_DATA_NOT_FOUND = 401;

    /**
     * プレイヤーデータが見つからない
     */
    public const PLAYER_DATA_NOT_FOUND = 402;

    /**
     * データ破損検出
     */
    public const DATA_CORRUPTED = 403;

    /**
     * 制約違反
     */
    public const CONSTRAINT_VIOLATION = 404;

    /**
     * データバージョン不一致
     */
    public const DATA_VERSION_MISMATCH = 405;

    /**
     * データ同期エラー
     */
    public const DATA_SYNC_ERROR = 406;

    // ========================================
    // タイムアウトエラー (500-599)
    // ========================================

    /**
     * リクエストタイムアウト
     */
    public const REQUEST_TIMEOUT = 501;

    /**
     * データベースタイムアウト
     */
    public const DATABASE_TIMEOUT = 502;

    /**
     * 外部APIタイムアウト
     */
    public const EXTERNAL_API_TIMEOUT = 503;

    /**
     * ロック取得タイムアウト
     */
    public const LOCK_TIMEOUT = 504;

    // ========================================
    // 設定/環境エラー (600-699)
    // ========================================

    /**
     * 設定エラー
     */
    public const CONFIGURATION_ERROR = 601;

    /**
     * 環境変数未設定
     */
    public const ENVIRONMENT_VARIABLE_MISSING = 602;

    /**
     * 機能無効
     */
    public const FEATURE_DISABLED = 603;

    /**
     * サポートされていないバージョン
     */
    public const UNSUPPORTED_VERSION = 604;

    // ========================================
    // 汎用システムエラー (700-999)
    // ========================================

    /**
     * ファイルが見つからない
     */
    public const FILE_NOT_FOUND = 701;

    /**
     * ファイル読み込み失敗
     */
    public const FILE_READ_FAILED = 702;

    /**
     * ファイル書き込み失敗
     */
    public const FILE_WRITE_FAILED = 703;

    /**
     * 権限不足
     */
    public const PERMISSION_DENIED = 704;

    /**
     * メモリ不足
     */
    public const OUT_OF_MEMORY = 705;

    /**
     * 不正な引数
     */
    public const INVALID_ARGUMENT = 706;

    /**
     * 未実装機能
     */
    public const NOT_IMPLEMENTED = 707;

    /**
     * サービス利用不可
     */
    public const SERVICE_UNAVAILABLE = 708;

    /**
     * レート制限超過
     */
    public const RATE_LIMIT_EXCEEDED = 709;

    /**
     * 不明なエラー
     */
    public const UNKNOWN_ERROR = 999;
}
