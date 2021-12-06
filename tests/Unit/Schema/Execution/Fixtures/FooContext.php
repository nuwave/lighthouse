<?php

namespace Tests\Unit\Schema\Execution\Fixtures;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FooContext implements GraphQLContext
{
    const FROM_FOO_CONTEXT = 'custom.context';

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

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
