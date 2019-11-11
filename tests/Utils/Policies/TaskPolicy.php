<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\User;

class TaskPolicy
{
    const ADMIN = 'admin';

    public function adminOnly(User $user): bool
    {
        return $user->name === self::ADMIN;
    }
}
