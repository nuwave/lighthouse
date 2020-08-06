<?php

namespace Tests\Unit\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Tests\TestCase;
use Tests\Unit\Execution\Arguments\Fixtures\Nested;
use Tests\Utils\Models\User;
use Tests\Utils\Models\WithoutRelationClassImport;

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

    public function testPartitionArgsExceptionBadRelationType(): void
    {
        $argumentSet = new ArgumentSet();

        $tasksRelation = new Argument();
        $argumentSet->arguments['users'] = $tasksRelation;

        $this->expectException(DefinitionException::class);

        ArgPartitioner::relationMethods(
            $argumentSet,
            new WithoutRelationClassImport(),
            HasMany::class
        );
    }
}
