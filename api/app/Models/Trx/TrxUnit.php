<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxUnit Model
 *
 * プレイヤーが所持するユニット（キャラクター）を管理するモデル
 */
class TrxUnit extends _BaseTrx
{
    protected $table = 'trx_unit';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（ユニットはIDで一意）
     */
    /** @var list<string> */
    protected array $uniqueKeys = ['id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_unit_id',
        'grade',
        'level',
        'level_exp',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'grade' => 'integer',
        'level' => 'integer',
        'level_exp' => 'integer',
    ];

    /**
     * trx_playerとのリレーション
     */
    /**
     * @return BelongsTo<TrxPlayer, $this>
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * ユニットIDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * プレイヤーIDを取得
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * マスターユニットIDを取得
     */
    public function getMstUnitId(): string
    {
        return $this->getAttribute('mst_unit_id');
    }

    /**
     * グレードを取得
     */
    public function getGrade(): int
    {
        return $this->getAttribute('grade');
    }

    /**
     * レベルを取得
     */
    public function getLevel(): int
    {
        return $this->getAttribute('level');
    }

    /**
     * レベル経験値を取得
     */
    public function getLevelExp(): int
    {
        return $this->getAttribute('level_exp');
    }

    /**
     * 削除フラグを取得
     */
    public function getIsDelete(): bool
    {
        return (bool) $this->getAttribute('is_delete');
    }

    /**
     * プレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * マスターユニットIDを設定
     */
    public function setMstUnitId(string $mstUnitId): void
    {
        $this->setAttribute('mst_unit_id', $mstUnitId);
    }

    /**
     * グレードを設定
     */
    public function setGrade(int $grade): void
    {
        $this->setAttribute('grade', $grade);
    }

    /**
     * レベルを設定
     */
    public function setLevel(int $level): void
    {
        $this->setAttribute('level', $level);
    }

    /**
     * レベル経験値を設定
     */
    public function setLevelExp(int $levelExp): void
    {
        $this->setAttribute('level_exp', $levelExp);
    }

    /**
     * 削除フラグを設定
     */
    public function setIsDelete(bool $isDelete): void
    {
        $this->setAttribute('is_delete', $isDelete);
    }

    /**
     * APIレスポンス用の配列に変換
     * id を trx_unit_id に変換
     *
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        // id を trx_unit_id にリネーム
        if (isset($array['id'])) {
            $array['trx_unit_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
