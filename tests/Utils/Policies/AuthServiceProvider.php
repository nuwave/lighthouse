<?php declare(strict_types=1);

namespace Tests\Utils\Policies;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as LaravelAuthServiceProvider;
use Tests\Utils\Models\Post;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

final class AuthServiceProvider extends LaravelAuthServiceProvider
{
    /** @var array<class-string<\Illuminate\Database\Eloquent\Model>, class-string> */
    protected $policies = [
        User::class => UserPolicy::class,
        Post::class => PostPolicy::class,
        Task::class => TaskPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
