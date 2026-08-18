<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case TEACHER = 'teacher';
    case STUDENT = 'student';
    case PARENT = 'parent';

    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
