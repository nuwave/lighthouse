<?php

namespace Tests\Utils\Mutations;

use GraphQL\Type\Definition\ResolveInfo;

class FooMutation
{
  public function bar($root, array $args, $context, ResolveInfo $resolve)
  {
    return 'bar';
  }
}

