<?php

namespace NexusAlbum\Enums;

/**
 * AlbumEntryType
 *
 * アルバムに記録する対象の種類
 *
 * 「一度でも入手・解放したこと」を記録する対象だけを持つ。
 * 数量を持つリソース（ダイヤ、スタミナ等）はアルバムの対象にならない。
 */
enum AlbumEntryType: string
{
    case UNIT = 'unit';             // ユニット
    case EQUIPMENT = 'equipment';   // 装備
    case ITEM = 'item';             // アイテム

    /**
     * 文字列から変換（未知の値はnull）
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * 全種別の値を取得
     *
     * @return array<int, string>
     */
    public static function allValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
