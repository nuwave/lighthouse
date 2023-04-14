<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class HttpGraphQLContext implements GraphQLContext
{
    /** An instance of the currently authenticated user. */
    public ?Authenticatable $user = null;

    public function __construct(
        /**
         * An instance of the incoming HTTP request.
         */
        public Request $request,
    ) {
        foreach (AuthServiceProvider::guards() as $guard) {
            $this->user = $request->user($guard);

            if (isset($this->user)) {
                break;
            }
        }
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function setUser(?Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function request(): ?Request
    {
        return $this->request;
    }
}
