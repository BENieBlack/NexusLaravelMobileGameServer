<?php

namespace App\Models\Trx;

use App\Models\Sys\SysPlayer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxPlayer Model
 *
 * プレイヤーのシャード固有データを管理するモデル
 * ※プレイヤーの基本情報はSysPlayerに保存される
 */
class TrxPlayer extends _BaseTrx
{
    protected $table = 'trx_player';

    /**
     * @var string テーブルのPK
     */
    protected $primaryKey = 'sys_player_id';

    /**
     * @var bool 自動インクリメント無効化（sys_player_idはsysデータベースで採番）
     */
    public $incrementing = false;

    /**
     * @var string プライマリキーの型
     */
    protected $keyType = 'int';

    /**
     * SELECTキー（この場合はPKと同じ）
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（プレイヤーはsys_player_idで一意）
     */
    protected array $uniqueKeys = ['sys_player_id'];

    protected $fillable = [
        'sys_player_id',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
    ];

    /**
     * sys_playerとのリレーション
     */
    public function sysPlayer(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'sys_player_id', 'id');
    }

    /**
     * システムプレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 主キーがsys_player_idであり、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
