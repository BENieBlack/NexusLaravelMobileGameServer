<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class AdmRole extends Model
{
    protected $connection = 'admin';

    protected $table = 'adm_role';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name'];

    public function deniedPages(): BelongsToMany
    {
        return $this->belongsToMany(
            AdmPage::class,
            'adm_permission',
            'adm_role_id',
            'adm_page_id',
        );
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(
            AdmAccount::class,
            'adm_account_role',
            'adm_role_id',
            'adm_account_id',
        );
    }
}
