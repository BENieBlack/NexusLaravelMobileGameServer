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
     * 
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（ユニットはIDで一意）
     * 
     * @var array
     */
    protected array $uniqueKeys = ['id'];

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

    protected $casts = [
        'sys_player_id' => 'integer',
        'grade' => 'integer',
        'level' => 'integer',
        'level_exp' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * trx_playerとのリレーション
     *
     * @return BelongsTo
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * ユニットIDを取得
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * プレイヤーIDを取得
     *
     * @return int
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * マスターユニットIDを取得
     *
     * @return string
     */
    public function getMstUnitId(): string
    {
        return $this->getAttribute('mst_unit_id');
    }

    /**
     * グレードを取得
     *
     * @return int
     */
    public function getGrade(): int
    {
        return $this->getAttribute('grade');
    }

    /**
     * レベルを取得
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->getAttribute('level');
    }

    /**
     * レベル経験値を取得
     *
     * @return int
     */
    public function getLevelExp(): int
    {
        return $this->getAttribute('level_exp');
    }

    /**
     * 削除フラグを取得
     *
     * @return bool
     */
    public function getIsDelete(): bool
    {
        return (bool)$this->getAttribute('is_delete');
    }

    /**
     * プレイヤーIDを設定
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * マスターユニットIDを設定
     *
     * @param int $mstUnitId
     * @return void
     */
    public function setMstUnitId(int $mstUnitId): void
    {
        $this->setAttribute('mst_unit_id', $mstUnitId);
    }

    /**
     * グレードを設定
     *
     * @param int $grade
     * @return void
     */
    public function setGrade(int $grade): void
    {
        $this->setAttribute('grade', $grade);
    }

    /**
     * レベルを設定
     *
     * @param int $level
     * @return void
     */
    public function setLevel(int $level): void
    {
        $this->setAttribute('level', $level);
    }

    /**
     * レベル経験値を設定
     *
     * @param int $levelExp
     * @return void
     */
    public function setLevelExp(int $levelExp): void
    {
        $this->setAttribute('level_exp', $levelExp);
    }

    /**
     * 削除フラグを設定
     *
     * @param bool $isDelete
     * @return void
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

