<?php

namespace App\Exceptions;

use LaravelWallet\Exceptions\WalletErrorCode;
use NexusResource\Exceptions\ResourceErrorCode;
use NexusStamina\Exceptions\StaminaErrorCode;

/**
 * GameErrorCode
 *
 * ゲームAPIのエラーコード定数定義（アプリケーション層: 5桁）
 * HTTPステータス299のレスポンスで使用するerror_codeの一覧
 *
 * エラーコード体系：
 * - インフラ/汎用エラー: 3桁以下（1-999） ← InfraErrorCodeで定義
 * - パッケージ層: 4桁（1000-9999） ← 各パッケージのErrorCodeクラスで定義
 * - アプリケーション層: 5桁（10000-99999） ← このクラスで定義
 *
 * インフラ層／パッケージ層で定義済みのコードは、このクラスに定数の別名だけを置く
 * （末尾の「他レイヤーで定義されたエラーコードへの参照」を参照）。値は二重に持たない。
 *
 * アプリケーション層エラーコード範囲:
 * - 10000-10999: 認証関連エラー (Auth)
 * - 11000-11999: プレイヤー関連エラー (Player)
 * - 12000-12999: ユニット関連エラー (Unit)
 * - 13000-13999: 装備関連エラー (Equipment)
 * - 14000-14999: クエスト関連エラー (Quest)
 * - 15000-15999: バトル関連エラー (Battle)
 * - 16000-16999: アプリ内課金関連エラー (InAppPurchase)
 * - 17000-17999: フレンド関連エラー (Friend)
 * - 18000-18999: ギルド関連エラー (Guild)
 * - 19000-19999: ガチャ関連エラー (Gacha)
 * - 20000-20999: メールボックス関連エラー (Mailbox)
 * - 99000-99999: アプリケーション汎用エラー
 *
 * インフラ層エラーコード（3桁以下）はInfraErrorCodeで定義：
 * - 100-199: データベースエラー
 * - 200-299: キャッシュ/Redisエラー
 * - 300-399: 暗号化/セキュリティエラー
 * - 400-499: データ整合性エラー（マスタデータ未検出等）
 * - 500-599: タイムアウトエラー
 * - 600-699: 設定/環境エラー
 * - 700-999: 汎用システムエラー
 *
 * パッケージ層エラーコード（4桁）は各パッケージで定義：
 * - nexus-wallet: 1000-1099 (WalletErrorCode) ✅実装済み
 * - nexus-stamina: 1100-1199 (StaminaErrorCode) ✅実装済み
 * - nexus-vip: 1200-1299 (VipErrorCode)
 * - nexus-friend: 1300-1399 (FriendErrorCode)
 * - nexus-guild: 1400-1499 (GuildErrorCode)
 * - nexus-mailbox: 1500-1599 (MailboxErrorCode)
 * - nexus-gacha: 1600-1699 (GachaErrorCode)
 * - nexus-login: 1700-1799 (LoginErrorCode)
 * - nexus-level: 1800-1899 (LevelErrorCode)
 * - nexus-resource: 1900-1999 (ResourceErrorCode) ✅実装済み
 * - nexus-billing: 2000-2099 (BillingErrorCode)
 */
class GameErrorCode
{
    // ========================================
    // 認証関連エラー (10000-10999)
    // ========================================
    const AUTHENTICATION_FAILED = 10001;

    const PLAYER_NOT_FOUND = 10002;

    const INVALID_TOKEN = 10003;

    const DEVICE_ALREADY_EXISTS = 10004;

    // ========================================
    // プレイヤー関連エラー (11000-11999)
    // ========================================
    const PLAYER_DATA_CORRUPTED = 11001;

    const PLAYER_NAME_INVALID = 11002;

    const PLAYER_LEVEL_MAX_REACHED = 11003;

    // ========================================
    // ユニット関連エラー (12000-12999)
    // ========================================
    const UNIT_NOT_FOUND = 12001;

    const UNIT_MAX_LEVEL_REACHED = 12002;

    const UNIT_EVOLUTION_FAILED = 12003;

    // ========================================
    // 装備関連エラー (13000-13999)
    // ========================================
    const EQUIPMENT_NOT_FOUND = 13001;

    const EQUIPMENT_MAX_LEVEL_REACHED = 13002;

    const EQUIPMENT_ENHANCE_FAILED = 13003;

    // ========================================
    // クエスト関連エラー (14000-14999)
    // ========================================
    const QUEST_NOT_FOUND = 14001;

    const QUEST_NOT_AVAILABLE = 14002;

    const QUEST_ALREADY_COMPLETED = 14003;

    // ========================================
    // バトル関連エラー (15000-15999)
    // ========================================
    const PARTY_FORMATION_INVALID = 15001;

    const BATTLE_RESULT_INVALID = 15002;

    // ========================================
    // アプリ内課金関連エラー (16000-16999)
    // ========================================
    const PRODUCT_NOT_FOUND = 16001;

    const PRODUCT_INACTIVE = 16002;

    const PURCHASE_LIMIT_EXCEEDED = 16003;

    const INVALID_PRODUCT_TYPE = 16004;

    const PRODUCT_ID_MISMATCH = 16005;

    const RECEIPT_VERIFICATION_FAILED = 16006;

    const PRICE_MISMATCH = 16007;

    // ========================================
    // フレンド関連エラー (17000-17999)
    // ========================================
    const FRIEND_REQUEST_ALREADY_EXISTS = 17001;

    const FRIEND_ALREADY_EXISTS = 17002;

    const FRIEND_REQUEST_NOT_FOUND = 17003;

    const TARGET_PLAYER_NOT_FOUND = 17004;

    const CANNOT_SEND_FRIEND_REQUEST_TO_SELF = 17005;

    const FRIEND_APPLY_NOT_FOUND = 17006;

    const NOT_AUTHORIZED_TO_ACCEPT = 17007;

    const FRIEND_APPLY_ALREADY_ACCEPTED = 17008;

    const FRIEND_APPLY_ALREADY_DELETED = 17009;

    const CANNOT_DELETE_SELF = 17010;

    const FRIEND_NOT_FOUND = 17011;

    const NOT_AUTHORIZED_TO_REJECT = 17012;

    const FRIEND_APPLY_ALREADY_REJECTED = 17013;

    // ========================================
    // ギルド関連エラー (18000-18999)
    // ========================================
    const GUILD_NOT_FOUND = 18001;

    const GUILD_NAME_ALREADY_EXISTS = 18002;

    const GUILD_FULL = 18003;

    const PLAYER_ALREADY_IN_GUILD = 18004;

    const PLAYER_NOT_IN_GUILD = 18005;

    const GUILD_APPLY_ALREADY_EXISTS = 18006;

    const GUILD_APPLY_NOT_FOUND = 18007;

    const GUILD_INVALID_STATUS = 18008;

    const GUILD_PERMISSION_DENIED = 18009;

    const GUILD_MASTER_CANNOT_LEAVE = 18010;

    const GUILD_MEMBER_NOT_FOUND = 18011;

    const GUILD_CREATE_FAILED = 18012;

    const GUILD_APPLY_FAILED = 18013;

    const GUILD_APPLY_ACCEPT_FAILED = 18014;

    const GUILD_APPLY_REJECT_FAILED = 18015;

    const GUILD_LEAVE_FAILED = 18016;

    // ========================================
    // ガチャ関連エラー (19000-19999)
    // ========================================
    const GACHA_NOT_FOUND = 19001;

    const GACHA_INACTIVE = 19002;

    const GACHA_NOT_AVAILABLE = 19003;

    const GACHA_DAILY_LIMIT_EXCEEDED = 19004;

    const GACHA_COST_NOT_FOUND = 19005;

    const GACHA_STEP_NOT_FOUND = 19006;

    const GACHA_CANDIDATE_NOT_FOUND = 19007;

    const GACHA_CANDIDATE_REQUIRED = 19008;

    const GACHA_INVALID_DRAW_COUNT = 19009;

    const GACHA_NO_PRIZES_AVAILABLE = 19010;

    // ========================================
    // メールボックス関連エラー (20000-20999)
    // ========================================
    const MAILBOX_NOT_FOUND = 20001;

    const MAILBOX_ALREADY_RECEIVED = 20002;

    const MAILBOX_NOT_OPENED = 20003;

    // ========================================
    // アプリケーション汎用エラー (99000-99999)
    // ========================================

    /**
     * データ検証エラー
     */
    const INVALID_PARAMETER = 99001;

    const VALIDATION_FAILED = 99002;

    /**
     * 未実装機能（アプリケーション層）
     */
    const NOT_IMPLEMENTED = 99900;

    /**
     * 内部エラー（アプリケーション層）
     *
     * Note: インフラ層のエラーはInfraErrorCode::UNKNOWN_ERROR (999)を使用
     */
    const INTERNAL_ERROR = 99999;

    // ========================================
    // 他レイヤーで定義されたエラーコードへの参照
    // ========================================
    //
    // 値の定義はインフラ層／パッケージ層が持ち、ここは参照だけを置く。
    // 呼び出し側がどのパッケージのコードかを意識せずに済むようにするための別名であり、
    // 値を二重に持たないため、パッケージ側を直せばこちらも追従する。

    /**
     * マスタデータ未検出（インフラ層で定義）
     */
    const MASTER_DATA_NOT_FOUND = InfraErrorCode::MASTER_DATA_NOT_FOUND;

    /**
     * スタミナ不足（nexus-staminaで定義）
     */
    const STAMINA_NOT_ENOUGH = StaminaErrorCode::INSUFFICIENT_STAMINA;

    /**
     * ダイヤモンド残高不足（nexus-resourceで定義）
     */
    const DIAMOND_NOT_ENOUGH = ResourceErrorCode::INSUFFICIENT_DIAMOND;

    /**
     * アイテム所持数不足（nexus-resourceで定義）
     */
    const ITEM_NOT_ENOUGH = ResourceErrorCode::INSUFFICIENT_ITEM;

    /**
     * 通貨残高不足（nexus-walletで定義）
     */
    const INSUFFICIENT_CURRENCY = WalletErrorCode::INSUFFICIENT_BALANCE;

    /**
     * ウォレット未検出（nexus-walletで定義）
     */
    const WALLET_NOT_FOUND = WalletErrorCode::WALLET_NOT_FOUND;

    /**
     * 無効なアイテム種別（nexus-resourceで定義）
     */
    const INVALID_ITEM_TYPE = ResourceErrorCode::INVALID_ITEM_TYPE;

    /**
     * 無効なリソース種別（nexus-resourceで定義）
     */
    const INVALID_RESOURCE_TYPE = ResourceErrorCode::INVALID_RESOURCE_TYPE;
}
