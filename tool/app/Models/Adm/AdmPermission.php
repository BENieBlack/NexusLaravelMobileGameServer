<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;

class AdmPermission extends Model
{
    protected $connection = 'admin';

    protected $table = 'adm_permission';

    protected $fillable = ['adm_role_id', 'adm_page_id'];
}
