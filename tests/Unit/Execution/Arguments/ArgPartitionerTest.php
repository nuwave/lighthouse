<?php

namespace Tests\Unit\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Tests\TestCase;
use Tests\Utils\Models\User;

class ArgPartitionerTest extends TestCase
{
    public function testPartitionArgsWithArResolvers(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $nested = new Argument();
        $nested->directives->push(new Nested());
        $argumentSet->arguments['nested'] = $nested;

        [$nestedArgs, $regularArgs] = ArgPartitioner::nestedArgResolvers($argumentSet, null);

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

        [$hasManyArgs, $regularArgs] = ArgPartitioner::relationMethods(
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

class Nested extends BaseDirective implements ArgResolver, Directive
{
    public function __invoke($root, $args)
    {
    }
}
