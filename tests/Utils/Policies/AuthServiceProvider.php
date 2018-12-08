<?php

namespace Tests\Utils\Policies;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as IlluminateAuthServiceProvider;
use Tests\Utils\Models\User;

class AuthServiceProvider extends IlluminateAuthServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
