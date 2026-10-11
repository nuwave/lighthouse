<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Http\Middleware;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;

/**
 * Attempt to authenticate the user, but don't do anything if they are not.
 */
class AttemptAuthentication
{
    public function __construct(
        protected AuthFactory $authFactory,
    ) {}

    public function handle(Request $request, \Closure $next, string ...$guards): mixed
    {
        $this->attemptAuthentication($guards);

        return $next($request);
    }

    /** @param  array<string>  $guards */
    protected function attemptAuthentication(array $guards): void
    {
        if ($guards === []) {
            $guards = AuthServiceProvider::guards();
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
