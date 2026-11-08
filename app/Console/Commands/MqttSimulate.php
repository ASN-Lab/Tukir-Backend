<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttSimulate extends Command
{
    protected $signature = 'mqtt:simulate {--rear=4.5 : Jarak belakang cm} {--side=5.2 : Jarak samping cm}';
    protected $description = 'Simulasikan telemetry device (tanpa hardware fisik)';

    public function handle(): int
    {
        $payload = json_encode([
            'garage_status' => 'OPEN',
            'system_mode' => 'PARKING_IN',
            'wifi_rssi' => -62,
            'rear_dist_cm' => (float) $this->option('rear'),
            'side_dist_cm' => (float) $this->option('side'),
        ]);

        $mqtt = new MqttClient(env('MQTT_HOST', '127.0.0.1'), (int) env('MQTT_PORT', 1883), 'tukir-simulate-' . uniqid());
        $settings = (new ConnectionSettings)->setConnectTimeout(10);
        if ($u = env('MQTT_USERNAME')) $settings->setUsername($u);
        if ($p = env('MQTT_PASSWORD')) $settings->setPassword($p);

        $mqtt->connect($settings, true);
        $mqtt->publish('tukir/TUKIR-GARAGE-01/telemetry', $payload, 0, false);
        $mqtt->publish('tukir/TUKIR-GARAGE-01/status', json_encode(['status' => 'ONLINE']), 0, true);

        $this->info("Published: $payload");
        $mqtt->disconnect();

        return self::SUCCESS;
    }
}