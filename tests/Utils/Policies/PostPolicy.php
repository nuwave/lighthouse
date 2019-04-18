<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class PostPolicy
{
    public function view(User $user, Post $post)
    {
        return $post->user_id === $user->getKey();
    }
}
