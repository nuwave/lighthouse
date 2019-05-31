<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\Post;
use Tests\Utils\Models\User;

class AuthServiceProvider extends \Illuminate\Foundation\Support\Providers\AuthServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Post::class => PostPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
