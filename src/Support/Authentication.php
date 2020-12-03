<?php

namespace Nuwave\Lighthouse\Support;

class Authentication
{
    /**
     * @return string|null
     */
    public static function getGuard()
    {
        $guards = array_keys(config('auth.guards'));

        if (! empty($guards)) {
            foreach ($guards as $guard) {
                if (auth()->guard($guard)->check()) {
                    return $guard;
                }
            }
        }

        return config('lighthouse.guard');
    }
}
