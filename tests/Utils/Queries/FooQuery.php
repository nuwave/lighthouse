<?php

namespace Tests\Utils\Queries;

use GraphQL\Type\Definition\ResolveInfo;

class FooQuery
{
  public function bar($root, array $args, $context, ResolveInfo $resolve)
  {
    return 'bar';
  }
}

