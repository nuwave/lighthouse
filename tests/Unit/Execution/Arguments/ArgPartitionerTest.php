<?php

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\ArgumentResolver;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Tests\TestCase;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;

class ArgPartitionerTest extends TestCase
{
    public function testPartitionsArgs(): void
    {
        $argumentSet = new ArgumentSet();

        $nested = new Argument();
        $nested->directives = new Collection([
            new Nested()
        ]);
        $argumentSet->arguments['nested']= $nested;

        $regular = new Argument();
        $regular->directives = new Collection();
        $argumentSet->arguments['regular']= $regular;

        $argPartitioner = new ArgPartitioner();
        [$regularArgs, $nestedArgs] = $argPartitioner->partitionResolverInputs(null, $argumentSet);

        $this->assertSame(
            ['regular' => $regular],
            $regularArgs->arguments
        );

        $this->assertSame(
            ['nested' => $nested],
            $nestedArgs->arguments
        );
    }
}

class Nested implements ArgumentResolver, Directive
{
    public function __invoke($root, ArgumentSet $args)
    {
    }

    public function name()
    {
    }
}
