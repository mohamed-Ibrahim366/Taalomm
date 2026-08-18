<?php

namespace App\Events;

use App\Models\User;

class PasswordChanged
{
    public function __construct(
        public readonly User $user,
    ) {}
}
