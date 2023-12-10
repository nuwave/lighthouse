<?php declare(strict_types=1);

namespace Tests\Utils\Policies;

use Illuminate\Auth\Access\Response;
use Tests\Utils\Models\User;

final class UserPolicy
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
     * @param User|array<string, mixed> ...$args
     */
    public function injectArgs(User $viewer, ...$args): bool
    {
        $injectedArgs = $args[0];
        if ($injectedArgs instanceof User){
            $injectedArgs = $args[1];
        }

        return $injectedArgs === ['foo' => 'bar'];
    }

    /**
     * @param User|array<string, mixed> ...$args
     */
    public function argsWithInjectedArgs(User $viewer, ...$args): bool
    {
        $injectedArgs = $args[0];
        $staticArgs = $args[1];

        if ($injectedArgs instanceof User){
            $injectedArgs = $args[1];
            $staticArgs = $args[2];
        }

        return $injectedArgs === ['foo' => 'dynamic']
            && $staticArgs === ['foo' => 'static'];
    }
}
