<?php


namespace Tests\Utils;


use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Tests\Utils\Models\User;

class CustomGuard implements Guard
{

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        // TODO: Implement check() method.
    }

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest()
    {
        // TODO: Implement guest() method.
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        return factory(User::class)->make([
            'name' => 'custom user'
        ]);
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id()
    {
        // TODO: Implement id() method.
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        // TODO: Implement validate() method.
    }

    /**
     * Set the current user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @return void
     */
    public function setUser(Authenticatable $user)
    {
        // TODO: Implement setUser() method.
    }
}
