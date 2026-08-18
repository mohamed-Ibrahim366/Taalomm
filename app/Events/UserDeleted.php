<?php

namespace App\Events;

use App\Models\User;

class UserDeleted
{
    public function __construct(
        public readonly User $user,
        public readonly ?User $deletedBy = null,
    ) {}
}
