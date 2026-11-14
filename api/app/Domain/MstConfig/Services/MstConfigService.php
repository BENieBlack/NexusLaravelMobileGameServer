<?php

namespace App\Domain\MstConfig\Services;

use App\Domain\MstConfig\Constants\MstConfigKey;
use App\Domain\MstConfig\Constants\MstConfigValueType;
use App\Domain\MstConfig\Contracts\MstConfigReaderInterface;
use App\Repositories\Mst\MstConfigRepository;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * MstConfigService
 *
 * mst_config の値を、型を検査して返す。
 *
 * 値が使えないとき（行が無い・value_type が違う・整数として読めない・下限未満）は、
 * 例外にせず MstConfigKey の既定値を返して警告ログを残す。
 * 設定の欠落（運営ツールのインポートは truncate + 再 INSERT なので、行が消え得る）で
 * login など全ての入口が止まるのを避けるため。欠落は警告ログで気づく。
 */
class MstConfigService implements MstConfigReaderInterface
{
    public function __construct(
        private readonly MstConfigRepository $mstConfigRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findStaminaRecoveryIntervalSeconds(): int
    {
        return $this->findInt(MstConfigKey::StaminaRecoveryIntervalSeconds);
    }

    /**
     * int の設定値を取得する
     *
     * @return int 常に MstConfigKey::minInt() 以上の整数
     *
     * @throws LogicException int でない設定のキーを渡したとき（呼び出し側のコードの誤り）
     */
    public function findInt(MstConfigKey $key): int
    {
        if ($key->valueType() !== MstConfigValueType::Int) {
            throw new LogicException("{$key->value} は int の設定ではない");
        }

        // テーブル全体は Redis（ミス時のみ mst DB）から 1 回で読み、メモリに持つ
        $row = $this->mstConfigRepository->selectById($key->value);

        if ($row === null) {
            return $this->fallback($key, '行が無い');
        }

        // 型の宣言が違う行は、値が数字に見えても使わない（設定ミスに気づけるようにする）
        if ($row->getValueType() !== MstConfigValueType::Int->value) {
            return $this->fallback($key, "value_type が int ではない ({$row->getValueType()})");
        }

        // '300' や '-1' は整数、'300.5'・'abc'・空文字・桁あふれは null になる
        $value = filter_var($row->getValue(), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            return $this->fallback($key, "整数として読めない ({$row->getValue()})");
        }

        // 0 以下を通すと、回復点数の計算でゼロ除算になる
        if ($value < $key->minInt()) {
            return $this->fallback($key, "下限 {$key->minInt()} 未満 ({$value})");
        }

        return $value;
    }

    /**
     * 既定値を返し、警告ログを残す
     */
    private function fallback(MstConfigKey $key, string $reason): int
    {
        Log::warning('mst_config の値が使えないため既定値を使う', [
            'key' => $key->value,
            'reason' => $reason,
            'default' => $key->defaultInt(),
        ]);

        return $key->defaultInt();
    }
}
