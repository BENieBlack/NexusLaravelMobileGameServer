<?php

namespace App\Domain\MstConfig\Constants;

use LogicException;

/**
 * MstConfigKey
 *
 * mst_config の設定 ID の一覧。
 * 各 case が「ID」「値の型」「行が無いとき・不正なときの既定値」「説明」を持つ。
 *
 * 既定値の置き場所はここだけ。シーダー（初期値 = 既定値）・読み出し側・テストは、
 * 数値を別に書かずここを参照する（値が複数箇所に残ってずれるのを防ぐ）。
 * 設定を足すときは case を足し、各 match に分岐を足す。
 */
enum MstConfigKey: string
{
    /** スタミナが 1 回復する間隔（秒） */
    case StaminaRecoveryIntervalSeconds = 'stamina_recovery_interval_seconds';

    /**
     * 値の型
     */
    public function valueType(): MstConfigValueType
    {
        return match ($this) {
            self::StaminaRecoveryIntervalSeconds => MstConfigValueType::Int,
        };
    }

    /**
     * 行が無い・型違い・不正なときに使う既定値（シーダーの初期値もこれ）
     *
     * 現在の設定は int だけ。型を増やすときは戻り値の型と読み出し口を足す。
     */
    public function defaultInt(): int
    {
        return match ($this) {
            // 従来のコード内の定数と同じ 5 分
            self::StaminaRecoveryIntervalSeconds => 300,
        };
    }

    /**
     * int 設定の下限（これ未満は不正として既定値に倒す）
     *
     * 回復間隔は割り算の分母に使うので、0 以下を許さない。
     */
    public function minInt(): int
    {
        return match ($this) {
            self::StaminaRecoveryIntervalSeconds => 1,
        };
    }

    /**
     * mst_config.value に入れる初期値（文字列）
     */
    public function initialValue(): string
    {
        return match ($this->valueType()) {
            MstConfigValueType::Int => (string) $this->defaultInt(),
            default => throw new LogicException("未対応の値の型: {$this->valueType()->value}"),
        };
    }

    /**
     * 設定の説明（mst_config.description。運用者向け）
     */
    public function description(): string
    {
        return match ($this) {
            self::StaminaRecoveryIntervalSeconds => 'スタミナが1回復する間隔（秒）。回復速度倍率で割る前の基本値。1以上の整数',
        };
    }
}
