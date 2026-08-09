<?php

namespace App\Http\Requests\Gacha;

use App\Http\Requests\_BaseRequest;

/**
 * DrawRequest
 *
 * ガチャ実行APIのリクエスト
 */
class DrawRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mst_gacha_id' => ['required', 'string', 'max:255'],
            'draw_count' => ['required', 'integer', 'in:1,10'],
            'selected_candidate_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mst_gacha_id.required' => 'ガチャIDは必須です',
            'mst_gacha_id.string' => 'ガチャIDは文字列である必要があります',
            'draw_count.required' => '実行回数は必須です',
            'draw_count.integer' => '実行回数は整数である必要があります',
            'draw_count.in' => '実行回数は1または10である必要があります',
            'selected_candidate_id.string' => '選択候補IDは文字列である必要があります',
        ];
    }

    /**
     * ガチャIDを取得
     *
     * @return string mst_gacha.id
     */
    public function getMstGachaId(): string
    {
        return (string) $this->input('mst_gacha_id');
    }

    /**
     * 実行回数を取得
     *
     * @return int 1連または10連
     */
    public function getDrawCount(): int
    {
        return (int) $this->input('draw_count');
    }

    /**
     * 選択候補IDを取得（ステップアップガチャのchoice用）
     *
     * @return string|null mst_gacha_step_bonus_content.id
     */
    public function getSelectedCandidateId(): ?string
    {
        return $this->input('selected_candidate_id');
    }
}
