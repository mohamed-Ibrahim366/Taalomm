<?php

namespace App\Events;

use App\Enums\UserStatus;
use App\Models\User;

class UserStatusChanged
{
    public function __construct(
        public readonly User $user,
        public readonly UserStatus $oldStatus,
        public readonly UserStatus $newStatus,
        public readonly ?User $changedBy = null,
    ) {}
}
