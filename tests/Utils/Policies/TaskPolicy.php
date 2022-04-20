<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class TaskPolicy
{
    public const ADMIN = 'admin';

    public function adminOnly(User $user): bool
    {
        return self::ADMIN === $user->name;
    }

    public function delete(User $user, Task $task): bool
    {
        $taskUser = $task->user;
        if (null === $taskUser) {
            return false;
        }

        return $user->id === $taskUser->id;
    }
}
