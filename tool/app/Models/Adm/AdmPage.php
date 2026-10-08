<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdmPage extends Model
{
    protected $connection = 'admin';

    protected $table = 'adm_page';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id'];

    /**
     * @return BelongsToMany<AdmRole, $this>
     */
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
