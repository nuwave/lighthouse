<?php declare(strict_types=1);

namespace Tests\Utils\Policies;

use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

final class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $post->user_id === $user->getKey();
    }

    public function delete(User $user, Post $post): bool
    {
        return (int) $post->user_id === (int) $user->getKey();
    }
}
