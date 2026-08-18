<?php

namespace App\Events;

use App\Models\User;

class ProfileUpdated
{
    public function __construct(
        public readonly User $user,
        public readonly array $changes = [],
    ) {}
}
