<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_code', 'device_name', 'owner_id', 'is_online',
        'garage_status', 'system_mode', 'wifi_rssi', 'last_seen'
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_seen' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // Serialisasi camelCase agar konsisten dengan klien (web/mobile)
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'deviceCode' => $this->device_code,
            'deviceName' => $this->device_name,
            'ownerId' => $this->owner_id,
            'owner' => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null,
            'isOnline' => (bool) $this->is_online,
            'garageStatus' => $this->garage_status,
            'systemMode' => $this->system_mode,
            'wifiRssi' => (int) $this->wifi_rssi,
            'lastSeen' => $this->last_seen?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
