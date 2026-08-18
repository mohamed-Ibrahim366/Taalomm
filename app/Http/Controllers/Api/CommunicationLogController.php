<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunicationLogResource;
use App\Models\CommunicationLog;
use Illuminate\Http\Request;

class CommunicationLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = CommunicationLog::with(['parent', 'teacher'])
            ->latest('logged_at')
            ->paginate($request->input('per_page', 15));

        return CommunicationLogResource::collection($logs);
    }

    public function store(Request $request)
    {
        $request->validate([
            'parent_id' => 'required|exists:users,id',
            'message' => 'required|string',
            'type' => 'required|in:sms,email,call,in_person',
            'logged_at' => 'required|date',
        ]);

        $log = CommunicationLog::create([
            'parent_id' => $request->parent_id,
            'teacher_id' => $request->user()->id,
            'message' => $request->message,
            'type' => $request->type,
            'logged_at' => $request->logged_at,
        ]);

        return new CommunicationLogResource($log->load(['parent', 'teacher']));
    }

    public function destroy(CommunicationLog $communicationLog)
    {
        $communicationLog->delete();
        return response()->json(['message' => 'Communication log deleted successfully.']);
    }
}
