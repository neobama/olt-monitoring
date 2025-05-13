<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OltDevice extends Model
{
    protected $fillable = [
        'name',
        'ip_address',
        'username',
        'password',
        'port',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    public function onus(): HasMany
    {
        return $this->hasMany(Onu::class);
    }
} 