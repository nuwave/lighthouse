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

    public function guestOnly($user = null): bool
    {
        return $user === null;
    }

    public function view(User $user, User $otherUser): bool
    {
        return $otherUser !== null;
    }

    public function dependingOnArg($user, bool $pass): bool
    {
        return $pass;
    }
}
