<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Onu extends Model
{
    protected $fillable = [
        'onu_id',
        'name',
        'is_online',
        'interface',
        'admin_state',
        'omcc_state',
        'phase_state',
        'last_seen',
        'olt_device_id',
        'serial_number',
        'description',
        'rx_power',
        'tx_power',
        'status',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
    ];

    public function oltDevice(): BelongsTo
    {
        return $this->belongsTo(OltDevice::class);
    }

    public function configuration(): HasOne
    {
        return $this->hasOne(OnuConfiguration::class);
    }
} 