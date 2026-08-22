<?php

namespace NexusResource\Exceptions;

/**
 * ResourceErrorCode
 *
 * nexus-resourceパッケージのエラーコード定義（4桁: 1900-1999）
 *
 * パッケージ層エラーコードルール：
 * - 4桁のコードを使用（再利用可能性を考慮）
 * - nexus-resourceの範囲: 1900-1999
 */
class ResourceErrorCode
{
    /**
     * アイテム残高不足
     * アイテムを消費しようとした際に所持数が不足している
     */
    public const INSUFFICIENT_ITEM = 1901;

    /**
     * ダイヤモンド残高不足
     * ダイヤモンドを消費しようとした際に残高が不足している
     */
    public const INSUFFICIENT_DIAMOND = 1902;

    /**
     * 無効なアイテム種別
     * 指定されたアイテム種別が存在しないか、この処理では扱えない
     */
    public const INVALID_ITEM_TYPE = 1903;

    /**
     * 無効なリソース種別
     * 指定されたリソース種別（ResourceType）が存在しないか、この処理では扱えない
     */
    public const INVALID_RESOURCE_TYPE = 1904;
}
