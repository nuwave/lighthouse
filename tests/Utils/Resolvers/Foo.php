<?php

namespace Tests\Utils\Resolvers;


use Nuwave\Lighthouse\Schema\ResolveInfo;

class Foo
{
    public function bar(ResolveInfo $resolveInfo)
    {

        $resolveInfo->result([
            $resolveInfo->field()->name() => "foo"
        ]);
        return $resolveInfo;
    }

    public function baz()
    {
        return array_get($args, 'directive.0');
    }
}
