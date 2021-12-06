<?php

namespace Tests\Utils\Policies;

use Illuminate\Auth\Access\Response;
use Tests\Utils\Models\User;

class UserPolicy
{
    public const SUPER_ADMIN = 'super admin';
    public const ADMIN = 'admin';

    public const SUPER_ADMINS_ONLY_MESSAGE = 'Only super admins allowed';

    public function adminOnly(User $user): bool
    {
        return $user->name === self::ADMIN;
    }

    public function superAdminOnly(User $user): Response
    {
        if ($user->name === self::SUPER_ADMIN) {
            return Response::allow();
        }

        return Response::deny(self::SUPER_ADMINS_ONLY_MESSAGE);
    }

    public function alwaysTrue(): bool
    {
        return true;
    }

    public function guestOnly(User $viewer = null): bool
    {
        return $viewer === null;
    }

    public function view(User $viewer, User $queriedUser): bool
    {
        return true;
    }

    public function dependingOnArg(User $viewer, bool $pass): bool
    {
        return $pass;
    }

    /**
     * @param  array<string, string>  $injectedArgs
     */
    public function injectArgs(User $viewer, array $injectedArgs): bool
    {
        return $injectedArgs === ['foo' => 'bar'];
    }

    /**
     * @param  array<string, string>  $injectedArgs
     * @param  array<string, string>  $staticArgs
     */
    public function argsWithInjectedArgs(User $viewer, array $injectedArgs, array $staticArgs): bool
    {
        return $injectedArgs === ['foo' => 'dynamic']
            && $staticArgs === ['foo' => 'static'];
    }
}
