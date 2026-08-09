<?php

namespace NexusMailbox\Constants;

/**
 * メールの優先度定数
 */
enum Priority: string
{
    case NORMAL = 'Normal';           // 通常
    case IMPORTANT = 'Important';     // 重要
    case URGENT = 'Urgent';           // 緊急

    /**
     * ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::NORMAL => '通常',
            self::IMPORTANT => '重要',
            self::URGENT => '緊急',
        };
    }

    /**
     * 文字列から変換
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
