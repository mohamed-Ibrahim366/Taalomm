<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RevisionMaterial;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RevisionMaterialController extends Controller
{
    public function store(Request $request, Course $course)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|max:20480', // max 20MB
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('courses/revisions', 'public');
            $revision = RevisionMaterial::create([
                'course_id' => $course->id,
                'title' => $request->title,
                'file_path' => $path,
                'file_size' => $request->file('file')->getSize(),
            ]);

            return response()->json([
                'message' => 'Revision material uploaded successfully.',
                'revision_material' => $revision,
            ], 201);
        }

        return response()->json(['message' => 'Failed to upload revision material.'], 400);
    }

    public function destroy(RevisionMaterial $revisionMaterial)
    {
        if (Storage::disk('public')->exists($revisionMaterial->file_path)) {
            Storage::disk('public')->delete($revisionMaterial->file_path);
        }
        $revisionMaterial->delete();

        return response()->json(['message' => 'Revision material deleted successfully.']);
    }
}
