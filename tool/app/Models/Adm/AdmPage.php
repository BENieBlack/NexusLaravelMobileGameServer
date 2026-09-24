<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class AdmPage extends Model
{
    protected $connection = 'admin';

    protected $table = 'adm_page';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id'];

    public function denyingRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdmRole::class,
            'adm_permission',
            'adm_page_id',
            'adm_role_id',
        );
    }
}
