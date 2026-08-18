<?php

namespace App\Events;

use App\Models\User;

class UserRestored
{
    public function __construct(
        public readonly User $user,
        public readonly ?User $restoredBy = null,
    ) {}
}
