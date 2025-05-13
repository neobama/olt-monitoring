<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnuConfiguration extends Model
{
    protected $fillable = [
        'onu_id',
        'vlan',
        'tcont',
        'vlan_name',
        'gem_port',
        'service_profile',
        'additional_config',
    ];

    protected $casts = [
        'additional_config' => 'array',
    ];

    public function onu(): BelongsTo
    {
        return $this->belongsTo(Onu::class);
    }
} 