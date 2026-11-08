<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasUuids;

    public $timestamps = false; // spec: created_at saja
    protected $fillable = [
        'device_id', 'action', 'rear_dist_cm', 'side_dist_cm',
        'fuzzy_risk_score', 'payload', 'created_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    // Serialisasi camelCase agar konsisten dengan klien
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'deviceId' => $this->device_id,
            'action' => $this->action,
            'rearDistCm' => $this->rear_dist_cm !== null ? (float) $this->rear_dist_cm : null,
            'sideDistCm' => $this->side_dist_cm !== null ? (float) $this->side_dist_cm : null,
            'fuzzyRiskScore' => $this->fuzzy_risk_score !== null ? (float) $this->fuzzy_risk_score : null,
            'payload' => $this->payload,
            'createdAt' => $this->created_at?->toISOString(),
            'device' => $this->device ? [
                'id' => $this->device->id,
                'deviceCode' => $this->device->device_code,
                'deviceName' => $this->device->device_name,
            ] : null,
        ];
    }
}
