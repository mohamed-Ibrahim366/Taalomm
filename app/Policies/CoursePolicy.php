<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function view(User $user, Course $course): bool
    {
        return $course->teacher_id === $user->id;
    }

    public function update(User $user, Course $course): bool
    {
        return $course->teacher_id === $user->id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $course->teacher_id === $user->id;
    }

    public function publish(User $user, Course $course): bool
    {
        return $course->teacher_id === $user->id;
    }

    
}