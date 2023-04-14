<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Auth\AuthServiceProvider;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UserGraphQLContext implements GraphQLContext
{
    /** An instance of the currently authenticated user. */
    public ?Authenticatable $user = null;

    public function __construct(
        protected AuthFactory $authFactory,
    ) {
        foreach (AuthServiceProvider::guards() as $guard) {
            $this->user = $this->authFactory->guard($guard)
                ->user();
            if ($this->user !== null) {
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
        return null;
    }
}
