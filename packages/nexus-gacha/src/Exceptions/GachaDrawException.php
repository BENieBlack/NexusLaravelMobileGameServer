<?php

namespace NexusGacha\Exceptions;

/**
 * GachaDrawException
 * 
 * ガチャ抽選処理で発生するエラーを表す例外クラス
 * 
 * エラーコードにより、エラーの種類を識別できます。
 */
class GachaDrawException extends \Exception
{
    /**
     * エラーコード定義
     */
    
    /** choice型でcandidate_idが指定されていない */
    public const CODE_MISSING_CANDIDATE_ID = 1001;
    
    /** 指定されたcandidate_idが無効（存在しない、またはボーナスIDと一致しない） */
    public const CODE_INVALID_CANDIDATE = 1002;
    
    /** random型で候補コンテンツが見つからない */
    public const CODE_NO_CANDIDATES = 1003;
    
    /** 景品データが見つからない */
    public const CODE_NO_PRIZES = 1004;
    
    /** レアリティ確率データが見つからない */
    public const CODE_NO_RARITY_RATES = 1005;
    
    /** サポートされていないselection_type */
    public const CODE_UNSUPPORTED_TYPE = 1006;
    
    /** 重み付きランダム抽選で候補が空 */
    public const CODE_EMPTY_ITEMS = 1007;
    
    /**
     * コンストラクタ
     * 
     * @param string $message エラーメッセージ
     * @param int $code エラーコード（CODE_*定数を使用）
     * @param \Throwable|null $previous 前の例外
     */
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
