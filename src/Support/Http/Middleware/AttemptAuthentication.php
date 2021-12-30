<?php

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;

/**
 * Attempt to authenticate the user, but don't do anything if they are not.
 */
class AttemptAuthentication
{
    /**
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $authFactory;

    public function __construct(AuthFactory $authFactory)
    {
        $this->authFactory = $authFactory;
    }

    /**
     * @param  string  ...$guards
     *
     * @return mixed Any kind of response
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $this->attemptAuthentication($guards);

        return $next($request);
    }

    /**
     * @param  array<string>  ...$guards
     */
    protected function attemptAuthentication(array $guards): void
    {
        if (empty($guards)) {
            $guards = [AuthServiceProvider::guard()];
        }

        foreach ($guards as $guard) {
            if ($this->authFactory->guard($guard)->check()) {
                // @phpstan-ignore-next-line passing null works fine here
                $this->authFactory->shouldUse($guard);

                return;
            }
        }
    }
}
