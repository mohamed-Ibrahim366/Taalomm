<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending'; // awaiting email verification / admin approval

    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
