<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 管理アカウントモデル
 *
 * adminデータベースのadm_accountテーブルに対応
 */
class AdmAccount extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * データベース接続名
     */
    protected $connection = 'admin';

    /**
     * テーブル名
     */
    protected $table = 'adm_account';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
        /** @var list<string> */
        protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
        /** @var list<string> */
        protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
