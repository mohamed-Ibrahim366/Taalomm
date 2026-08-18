<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ParentResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParentController extends Controller
{
    private function normalizeStudentIds(Request $request): array
    {
        $rawStudents = $request->input('students', $request->input('studentIds', []));

        if (!is_array($rawStudents)) {
            return [];
        }

        $studentIds = array_map(function ($student) {
            if (is_array($student)) {
                return (int) ($student['id'] ?? $student['value'] ?? 0);
            }

            return (int) $student;
        }, $rawStudents);

        return array_values(array_unique(array_filter($studentIds, static fn (int $id) => $id > 0)));
    }

    private function parentResource(User $parent): ParentResource
    {
        return new ParentResource($parent->fresh()->load('students'));
    }

    public function index(Request $request)
    {
        $parents = User::where('role', UserRole::PARENT)
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->with('students')
            ->paginate($request->input('per_page', 15));

        return ParentResource::collection($parents);
    }

    public function store(Request $request)
    {
        $studentIds = $this->normalizeStudentIds($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'students' => 'nullable|array',
            'studentIds' => 'nullable|array',
            'students.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', UserRole::STUDENT->value)),
            ],
        ]);

        $parent = DB::transaction(function () use ($request, $studentIds) {
            $parent = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => UserRole::PARENT->value,
                'phone' => $request->phone,
            ]);

            $parent->students()->sync($studentIds);

            return $parent;
        });

        return $this->parentResource($parent);
    }

    public function show(User $parent)
    {
        if (!$parent->isParent()) {
            abort(404, 'Parent not found.');
        }

        return new ParentResource($parent->load('students'));
    }

    public function update(Request $request, User $parent)
    {
        if (!$parent->isParent()) {
            abort(404, 'Parent not found.');
        }

        $studentIds = $this->normalizeStudentIds($request);

        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $parent->id,
            'phone' => 'nullable|string|max:20',
            'students' => 'nullable|array',
            'studentIds' => 'nullable|array',
            'students.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', UserRole::STUDENT->value)),
            ],
        ]);

        DB::transaction(function () use ($request, $parent, $studentIds) {
            $parent->update($request->only(['name', 'email', 'phone']));

            if ($request->has('students') || $request->has('studentIds')) {
                $parent->students()->sync($studentIds);
            }
        });

        return $this->parentResource($parent);
    }

    public function destroy(User $parent)
    {
        if (!$parent->isParent()) {
            abort(404, 'Parent not found.');
        }

        DB::transaction(function () use ($parent) {
            $parent->students()->detach();
            $parent->delete();
        });

        return response()->json(['message' => 'Parent deleted successfully.']);
    }

    public function students(User $parent)
    {
        if (!$parent->isParent()) {
            abort(404, 'Parent not found.');
        }

        return UserResource::collection($parent->students);
    }
}
