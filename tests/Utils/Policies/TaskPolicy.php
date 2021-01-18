<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class TaskPolicy
{
    public const ADMIN = 'admin';

    public function adminOnly(User $user): bool
    {
        return $user->name === self::ADMIN;
    }

    public function delete(User $user, Task $task): bool
    {
        $taskUser = $task->user;
        if ($taskUser === null) {
            return false;
        }

        return $user->id === $taskUser->id;
    }
}
