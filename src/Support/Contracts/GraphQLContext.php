<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Singleton accessible in all resolvers.
 *
 * @api
 */
interface GraphQLContext
{
    /**
     * Get an instance of the authenticated user.
     *
     * May be null since some fields may be accessible without authentication.
     */
    public function user(): ?Authenticatable;

    /** Set the authenticated user. */
    public function setUser(?Authenticatable $user): void;

    /**
     * Get an instance of the current HTTP request.
     *
     * May be null if GraphQL is run outside the context of an HTTP request.
     */
    public function request(): ?Request;
}
