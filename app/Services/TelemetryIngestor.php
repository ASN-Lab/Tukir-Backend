<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Device;

/**
 * Menyimpan payload telemetry/status dari broker MQTT (via EMQX webhook
 * atau worker mqtt:listen) ke database.
 */
class TelemetryIngestor
{
    public function __construct(private FuzzyService $fuzzyService) {}

    public function telemetry(string $deviceCode, array $data): void
    {
        $device = Device::where('device_code', $deviceCode)->first();
        if (! $device) {
            return;
        }

        $rearDist = $data['rear_dist_cm'] ?? null;
        $sideDist = $data['side_dist_cm'] ?? null;

        $fuzzyScore = null;
        if ($rearDist !== null && $sideDist !== null) {
            $fuzzyResult = $this->fuzzyService->evaluate((float) $rearDist, (float) $sideDist);
            $fuzzyScore = $fuzzyResult['fuzzy_risk_score'];
        }

        $device->update([
            'is_online' => true,
            'garage_status' => $data['garage_status'] ?? $device->garage_status,
            'system_mode' => $data['system_mode'] ?? $device->system_mode,
            'wifi_rssi' => $data['wifi_rssi'] ?? $device->wifi_rssi,
            'last_seen' => now(),
        ]);

        ActivityLog::create([
            'device_id' => $device->id,
            'action' => 'TELEMETRY_RECEIVED',
            'rear_dist_cm' => $rearDist,
            'side_dist_cm' => $sideDist,
            'fuzzy_risk_score' => $fuzzyScore,
            'payload' => $data,
        ]);
    }

    public function status(string $deviceCode, array $data): void
    {
        $isOnline = ($data['status'] ?? '') === 'ONLINE';

        $device = Device::where('device_code', $deviceCode)->first();
        if (! $device) {
            return;
        }

        $device->update([
            'is_online' => $isOnline,
            'last_seen' => now(),
        ]);

        ActivityLog::create([
            'device_id' => $device->id,
            'action' => $isOnline ? 'HARDWARE_ONLINE' : 'HARDWARE_OFFLINE',
            'payload' => $data,
        ]);
    }
}
