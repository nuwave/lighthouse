<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\User;

class UserPolicy
{
    public function adminOnly(User $user): bool
    {
        return 'admin' === $user->name;
    }

    public function alwaysTrue(): bool
    {
        return true;
    }

    public function guestOnly($user = null): bool
    {
        return null === $user;
    }

    public function dependingOnArg($user, bool $pass): bool
    {
        return $pass;
    }
}
