<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxEquipment Model
 *
 * プレイヤーが所持する装備を管理するモデル
 *
 * @property int $id
 * @property int $sys_player_id
 * @property string $mst_equipment_id
 * @property int $grade
 * @property int $level
 * @property int $level_exp
 * @property string $created_at
 * @property string $updated_at
 */
class TrxEquipment extends _BaseTrx
{
    protected $table = 'trx_equipment';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（装備はIDで一意）
     *
     * @var array<int, string>
     */
    /** @var list<string> */
    protected array $uniqueKeys = ['id'];

    /**
     * @var array<int, string>
     */
    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_equipment_id',
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
     *
     * @return BelongsTo<TrxPlayer, TrxEquipment>
     */
    /**
     * @return BelongsTo<TrxPlayer, $this>
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * 装備IDを取得
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
     * マスター装備IDを取得
     */
    public function getMstEquipmentId(): string
    {
        return $this->getAttribute('mst_equipment_id');
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
     * マスター装備IDを設定
     */
    public function setMstEquipmentId(string $mstEquipmentId): void
    {
        $this->setAttribute('mst_equipment_id', $mstEquipmentId);
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
     * id を trx_equipment_id に変換
     *
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        // id を trx_equipment_id にリネーム
        if (isset($array['id'])) {
            $array['trx_equipment_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
