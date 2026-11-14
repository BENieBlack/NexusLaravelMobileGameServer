<?php

namespace App\Domain\MstConfig\Constants;

/**
 * MstConfigValueType
 *
 * mst_config.value_type に入る値。マイグレーションの enum と同じ並び。
 */
enum MstConfigValueType: string
{
    case Int = 'int';
    case Float = 'float';
    case Bool = 'bool';
    case String = 'string';
}
