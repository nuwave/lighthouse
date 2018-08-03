<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\User;

class UserPolicy
{
    public function adminOnly(User $user): bool
    {
        return $user->name === 'admin';
    }

    public function alwaysTrue(): bool
    {
        return true;
    }
}
