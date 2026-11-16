<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // END_USER hanya melihat device miliknya
        $query = Device::with('owner:id,name,email');

        if ($user->role === 'END_USER') {
            $query->where('owner_id', $user->id);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function control(Request $request, string $id)
    {
        $data = $request->validate([
            'command' => 'required|in:OPEN,CLOSE,POWER_ON,POWER_OFF',
        ]);

        $device = Device::findOrFail($id);
        $user = $request->user();

        // RBAC: SUPER_ADMIN boleh semua, selain itu hanya pemilik
        if ($user->role !== 'SUPER_ADMIN' && $device->owner_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Publish command via MQTT ke topic control device
        $mqttError = null;
        try {
            $published = $this->publishMqtt($device->device_code, $data['command']);
        } catch (\Throwable $e) {
            $published = false;
            $mqttError = $e->getMessage();
        }

        // Optimistic update state (firmware akan konfirmasi via telemetry saat terpasang)
        if ($data['command'] === 'OPEN') {
            $device->update(['garage_status' => 'OPEN']);
        } elseif ($data['command'] === 'CLOSE') {
            $device->update(['garage_status' => 'CLOSED', 'system_mode' => 'STANDBY']);
        } elseif ($data['command'] === 'POWER_ON') {
            $device->update(['system_mode' => 'PARKING_IN']);
        } elseif ($data['command'] === 'POWER_OFF') {
            $device->update(['system_mode' => 'STANDBY']);
        }

        // Catat activity log
        $action = match ($data['command']) {
            'OPEN' => 'GARAGE_OPEN',
            'CLOSE' => 'GARAGE_CLOSE',
            'POWER_ON' => 'POWER_ON',
            'POWER_OFF' => 'POWER_OFF',
        };

        ActivityLog::create([
            'device_id' => $device->id,
            'action' => $action,
            'payload' => [
                'command' => $data['command'],
                'triggered_by' => $user->email,
                'mqtt_published' => $published,
                'mqtt_error' => $mqttError,
            ],
        ]);

        return response()->json([
            'status' => $published ? 'command_sent' : 'command_failed',
            'command' => $data['command'],
            'mqtt_published' => $published,
            'mqtt_error' => $mqttError,
        ]);
    }

    private function publishMqtt(string $deviceCode, string $command): bool
    {
        $host = config('mqtt.host');
        $apiKey = env('EMQX_API_KEY');
        $apiSecret = env('EMQX_API_SECRET');

        if (!$apiKey || !$apiSecret) {
            throw new \Exception('EMQX API Credentials (API_KEY/SECRET) are not configured in Wasmer Secrets.');
        }

        // Endpoint EMQX Cloud REST API (Standard port 18083 for HTTP API)
        $url = "https://$host:18083/api/v5/publish";

        $response = Http::withBasicAuth($apiKey, $apiSecret)
            ->post($url, [
                'topic' => "tukir/$deviceCode/command",
                'payload' => json_encode([
                    'command' => $command,
                    'timestamp' => now()->toIso8601String(),
                ]),
                'qos' => 1,
                'retain' => false,
            ]);

        if ($response->failed()) {
            throw new \Exception('EMQX API Failure: ' . $response->body());
        }

        return true;
    }
}
