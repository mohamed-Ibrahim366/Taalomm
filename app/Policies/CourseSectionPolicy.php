<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;

class CourseSectionPolicy
{
    /**
     * Determine whether the user can view sections.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create sections.
     */
    public function create(User $user, Course $course): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id);
    }

    /**
     * Determine whether the user can update the section.
     */
    public function update(User $user, CourseSection $section): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $section->course->teacher_id === $user->id);
    }

    /**
     * Determine whether the user can delete the section.
     */
    public function delete(User $user, CourseSection $section): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $section->course->teacher_id === $user->id);
    }
}
