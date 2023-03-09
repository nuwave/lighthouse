<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Context implements GraphQLContext
{
    /**
     * An instance of the currently authenticated user.
     */
    public ?Authenticatable $user;

    public function __construct(
        /**
         * An instance of the incoming HTTP request.
         */
        public Request $request,
    ) {
        foreach (AuthServiceProvider::guards() as $guard) {
            $user = $request->user($guard);

            if (null !== $user) {
                $this->user = $user;

                break;
            }
        }
    }

    /**
     * Get instance of authenticated user.
     *
     * May be null since some fields may be accessible without authentication.
     */
    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function setUser(?Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function request(): Request
    {
        return $this->request;
    }
}
