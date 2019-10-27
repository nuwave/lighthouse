<?php

namespace Tests\Unit\Execution\Arguments;

use Tests\TestCase;
use Tests\Utils\Models\User;
use Nuwave\Lighthouse\Execution\ArgumentResolver;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;

class ArgPartitionerTest extends TestCase
{
    public function testPartitionArgsWithArgumentResolvers(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $nested = new Argument();
        $nested->directives->push(new Nested());
        $argumentSet->arguments['nested'] = $nested;

        [$regularArgs, $nestedArgs] = ArgPartitioner::nestedArgumentResolvers($argumentSet, null);

        $this->assertSame(
            ['regular' => $regular],
            $regularArgs->arguments
        );

        $this->assertSame(
            ['nested' => $nested],
            $nestedArgs->arguments
        );
    }

    public function testPartitionArgsThatMatchRelationMethods(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $tasksRelation = new Argument();
        $argumentSet->arguments['tasks'] = $tasksRelation;

        [$regularArgs, $hasManyArgs] = ArgPartitioner::relationMethods(
            $argumentSet,
            new User(),
            HasMany::class
        );

        $this->assertSame(
            ['regular' => $regular],
            $regularArgs->arguments
        );

        $this->assertSame(
            ['tasks' => $tasksRelation],
            $hasManyArgs->arguments
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
