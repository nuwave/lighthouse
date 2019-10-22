<?php

namespace Tests\Unit\Execution\Arguments;

use Tests\TestCase;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedAfter;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNestedBefore;

class ArgPartitionerTest extends TestCase
{
    public function testPartitionsArgs(): void
    {
        $argumentSet = new ArgumentSet();

        $before = new Argument();
        $before->directives = new Collection([
            new Before()
        ]);
        $argumentSet->arguments['before']= $before;

        $regular = new Argument();
        $regular->directives = new Collection();
        $argumentSet->arguments['regular']= $regular;

        $after = new Argument();
        $after->directives = new Collection([
            new After()
        ]);
        $argumentSet->arguments['after']= $after;

        $argPartitioner = new ArgPartitioner();
        [$beforeArgs, $regularArgs, $afterArgs] = $argPartitioner->partitionResolverInputs(null, $argumentSet);

        $this->assertSame(
            ['before' => $before],
            $beforeArgs
        );

        $this->assertSame(
            ['regular' => $regular],
            $regularArgs
        );

        $this->assertSame(
            ['after' => $after],
            $afterArgs
        );
    }
}

class Before implements ResolveNestedBefore
{
    public function __invoke($root, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
    }

    /**
     * Name of the directive as used in the schema.
     *
     * @return string
     */
    public function name()
    {
        // TODO: Implement name() method.
    }
}

class After implements ResolveNestedAfter
{
    public function __invoke($root, $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
    }

    /**
     * Name of the directive as used in the schema.
     *
     * @return string
     */
    public function name()
    {
        // TODO: Implement name() method.
    }
}
