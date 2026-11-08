<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Services\FuzzyService;
use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Mendengarkan topik telemetry MQTT dan memproses data dengan Fuzzy Logic Engine';

    public function handle(FuzzyService $fuzzyService)
    {
        $server   = env('MQTT_HOST', '127.0.0.1');
        $port     = env('MQTT_PORT', 1883);
        $clientId = 'tukir-laravel-worker-' . uniqid();

        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
            ->setConnectTimeout(10);

        if ($username = env('MQTT_USERNAME')) {
            $settings->setUsername($username);
        }
        if ($password = env('MQTT_PASSWORD')) {
            $settings->setPassword($password);
        }

        $mqtt = new MqttClient($server, (int)$port, $clientId);

        $this->info("Menghubungkan ke MQTT Broker [$server:$port]...");
        $mqtt->connect($settings, true);

        // Subscribe telemetry & status
        $mqtt->subscribe('tukir/+/telemetry', function ($topic, $message) use ($fuzzyService) {
            $this->handleTelemetry($topic, $message, $fuzzyService);
        }, 0);

        $mqtt->subscribe('tukir/+/status', function ($topic, $message) {
            $this->handleStatus($topic, $message);
        }, 0);

        // Heartbeat monitor: Set offline if no telemetry/status for 45s
        $this->info("MQTT Worker siap & mendengarkan topik...");
        
        // Cek status stale secara periodik
        while (true) {
            $mqtt->loop(false);
            $this->checkStaleDevices();
            sleep(10); // Cek setiap 10 detik
        }
    }

    private function checkStaleDevices()
    {
        $staleDevices = Device::where('is_online', true)
            ->where('last_seen', '<', now()->subSeconds(45))
            ->get();

        foreach ($staleDevices as $device) {
            $device->update(['is_online' => false]);
            $this->warn("Device [{$device->device_code}] offline (stale data).");
            
            // Broadcast ke socket kalau pake event listener
            // (Laravel broadcast harus di-trigger jika diperlukan)
        }
    }

    private function handleTelemetry(string $topic, string $message, FuzzyService $fuzzyService)
    {
        $parts = explode('/', $topic);
        $deviceCode = $parts[1] ?? null;
        if (!$deviceCode) return;

        $data = json_decode($message, true);
        if (!$data) {
            $this->error("Invalid JSON dari $topic: $message");
            return;
        }

        $device = Device::where('device_code', $deviceCode)->first();
        if (!$device) return;

        $rearDist = $data['rear_dist_cm'] ?? null;
        $sideDist = $data['side_dist_cm'] ?? null;

        $fuzzyScore = null;
        if ($rearDist !== null && $sideDist !== null) {
            $fuzzyResult = $fuzzyService->evaluate((float)$rearDist, (float)$sideDist);
            $fuzzyScore = $fuzzyResult['fuzzy_risk_score'];
        }

        // Update Device State
        $device->update([
            'is_online' => true,
            'garage_status' => $data['garage_status'] ?? $device->garage_status,
            'system_mode' => $data['system_mode'] ?? $device->system_mode,
            'wifi_rssi' => $data['wifi_rssi'] ?? $device->wifi_rssi,
            'last_seen' => now(),
        ]);

        // Simpan Log ke DB (Activity & Telemetry Logs)
        ActivityLog::create([
            'device_id' => $device->id,
            'action' => 'TELEMETRY_RECEIVED',
            'rear_dist_cm' => $rearDist,
            'side_dist_cm' => $sideDist,
            'fuzzy_risk_score' => $fuzzyScore,
            'payload' => $data,
        ]);

        $this->info("Telemetry [$deviceCode]: Rear=$rearDist cm, Side=$sideDist cm -> Fuzzy Risk: $fuzzyScore%");
    }

    private function handleStatus(string $topic, string $message)
    {
        $parts = explode('/', $topic);
        $deviceCode = $parts[1] ?? null;
        if (!$deviceCode) return;

        $data = json_decode($message, true);
        $isOnline = ($data['status'] ?? '') === 'ONLINE';

        $device = Device::where('device_code', $deviceCode)->first();
        if ($device) {
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
}
