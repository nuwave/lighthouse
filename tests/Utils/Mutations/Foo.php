<?php

namespace Tests\Utils\Mutations;

use Nuwave\Lighthouse\Support\Schema\GraphQLMutation;

class Foo extends GraphQLMutation
{
    /**
     * Resolve the mutation.
     *
     * @return mixed
     */
    public function resolve()
    {
        return array_get($this->args, 'bar').' '.array_get($this->args, 'baz');
    }
}
