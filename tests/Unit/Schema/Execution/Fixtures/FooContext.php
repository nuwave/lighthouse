<?php

namespace Tests\Unit\Schema\Execution\Fixtures;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class FooContext implements GraphQLContext
{
    public const FROM_FOO_CONTEXT = 'custom.context';

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function user(): User
    {
        return new User();
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function foo(): string
    {
        return self::FROM_FOO_CONTEXT;
    }
}
