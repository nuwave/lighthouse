<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\User;

class UserPolicy
{
    const ADMIN = 'admin';

    public function adminOnly(User $user): bool
    {
        return $user->name === self::ADMIN;
    }

    public function alwaysTrue(): bool
    {
        return true;
    }

    public function guestOnly($viewer = null): bool
    {
        return $viewer === null;
    }

    public function view(User $viewer, User $queriedUser): bool
    {
        return true;
    }

    public function dependingOnArg($viewer, bool $pass): bool
    {
        return $pass;
    }

    public function severalArgs($user, array $injectedArgs): bool
    {
        return $injectedArgs['foo'] === 'bar';
    }

    public function argsWithInjectedArgs($user, array $injectedArgs, array $args): bool
    {
        return $args['argFoo'] === 'argBar' && $injectedArgs['foo'] === 'bar';
    }
}
