<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Simps\Mqtt\Client;
use Simps\Mqtt\Config\ClientConfig;

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
        $published = $this->publishMqtt($device->device_code, $data['command']);

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
            ],
        ]);

        return response()->json([
            'status' => $published ? 'command_sent' : 'command_queued',
            'command' => $data['command'],
            'mqtt_published' => $published,
        ]);
    }

    private function publishMqtt(string $deviceCode, string $command): bool
    {
        try {
            // Menggunakan Simps\MQTT\Client dengan WebSocket
            $server = Cache::get('mqtt_tunnel_host') ?? env('MQTT_HOST', '127.0.0.1');
            $port = (int) env('MQTT_PORT', 443);
            $clientId = 'tukir-laravel-api-' . uniqid();

            $config = (new ClientConfig())
                ->setClientId($clientId)
                ->setUserName(env('MQTT_USERNAME', 'tukir_user'))
                ->setPassword(env('MQTT_PASSWORD', 'tukir_pass'))
                ->setKeepAlive(30);

            // Gunakan WebSocketClient untuk port 443/9001
            $mqtt = new \Simps\MQTT\WebSocketClient($server, $port, $config, '/mqtt', false);

            $payload = json_encode([
                'command' => $command,
                'timestamp' => now()->toIso8601String(),
            ]);

            $topic = "tukir/device/$deviceCode/command";
            $mqtt->publish($topic, $payload, 1);
            $mqtt->close();

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
