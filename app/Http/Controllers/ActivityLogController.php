<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('device:id,device_code,device_name')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json($logs);
    }
}
