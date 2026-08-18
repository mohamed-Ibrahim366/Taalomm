<?php

namespace App\Events;

use App\Models\User;

class UserUpdated
{
    public function __construct(
        public readonly User $user,
        public readonly ?User $updatedBy = null,
        public readonly array $changes = [],
    ) {}
}
