<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    public function store(Request $request, Lesson $lesson)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:20480', // max 20MB
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('lessons/resources', 'public');
            $resource = Resource::create([
                'lesson_id' => $lesson->id,
                'title' => $request->title,
                'file_path' => $path,
                'file_type' => $request->file('file')->getClientOriginalExtension(),
                'file_size' => $request->file('file')->getSize(),
            ]);

            return response()->json([
                'message' => 'Resource uploaded successfully.',
                'resource' => $resource,
            ], 201);
        }

        return response()->json(['message' => 'Failed to upload resource.'], 400);
    }

    public function destroy(Resource $resource)
    {
        if (Storage::disk('public')->exists($resource->file_path)) {
            Storage::disk('public')->delete($resource->file_path);
        }
        $resource->delete();

        return response()->json(['message' => 'Resource deleted successfully.']);
    }

    public function byLesson(Lesson $lesson)
    {
        $resources = Resource::where('lesson_id', $lesson->id)->get();
        return response()->json(['data' => $resources]);
    }
}
