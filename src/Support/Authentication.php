<?php

namespace Nuwave\Lighthouse\Support;

use Illuminate\Support\Facades\Auth;

class Authentication
{
    /**
     * @return \Illuminate\Config\Repository|\Illuminate\Contracts\Foundation\Application|\Laravel\Lumen\Application|mixed
     */
    public static function getGuard()
    {
        $customGuards = config('lighthouse.custom_guards');

        if (! empty($customGuards)) {
            foreach ($customGuards as $customGuard) {
                if (Auth::guard($customGuard)->check()) {
                    return $customGuard;
                }
            }
        }

        return config('lighthouse.guard');
    }
}
