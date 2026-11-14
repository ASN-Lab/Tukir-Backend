<?php

namespace App\Http\Controllers;

use App\Services\TelemetryIngestor;
use Illuminate\Http\Request;

/**
 * Menerima payload MQTT dari EMQX Rule Engine (HTTP POST webhook).
 */
class IngestController extends Controller
{
    public function __invoke(Request $request, TelemetryIngestor $ingestor)
    {
        $request->validate([
            'topic' => 'required|string',
            'payload' => 'required|string',
        ]);

        if ($request->header('X-Ingest-Secret') !== config('mqtt.webhook_secret')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Format topik: tukir/{device_code}/{telemetry|status}
        $parts = explode('/', $request->topic);
        $deviceCode = $parts[1] ?? null;
        $kind = $parts[2] ?? null;

        $data = json_decode($request->payload, true);
        if (! $deviceCode || ! is_array($data)) {
            return response()->json(['status' => 'ignored']);
        }

        match ($kind) {
            'telemetry' => $ingestor->telemetry($deviceCode, $data),
            'status' => $ingestor->status($deviceCode, $data),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }
}
