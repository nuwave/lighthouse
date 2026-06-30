<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgPartitioner;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Tests\TestCase;
use Tests\Unit\Execution\Arguments\Fixtures\Nested;
use Tests\Unit\Execution\Arguments\Fixtures\SaveAwareNested;
use Tests\Utils\Models\User;
use Tests\Utils\Models\WithoutRelationClassImport;

final class ArgPartitionerTest extends TestCase
{
    public function testPartitionArgsWithArgResolvers(): void
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
            $regularArgs->arguments,
        );

        $this->assertSame(
            ['nested' => $nested],
            $nestedArgs->arguments,
        );
    }

    public function testPartitionArgsThatMatchRelationMethods(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $tasksRelation = new Argument();
        $tasksRelation->value = new ArgumentSet();
        $argumentSet->arguments['tasks'] = $tasksRelation;

        $postsRelation = new Argument();
        $postsRelation->value = null;
        $argumentSet->arguments['posts'] = $postsRelation;

        [$hasManyArgs, $regularArgs] = ArgPartitioner::relationMethods(
            $argumentSet,
            new User(),
            HasMany::class,
        );

        $this->assertSame(
            ['regular' => $regular],
            $regularArgs->arguments,
        );

        $this->assertSame(
            ['tasks' => $tasksRelation],
            $hasManyArgs->arguments,
        );
    }

    public function testArgsMatchingNonRelationMethod(): void
    {
        $argumentSet = new ArgumentSet();

        /** @see User::nonRelationPrimitive() */
        $nonRelationPrimitive = new Argument();
        $argumentSet->arguments['nonRelationPrimitive'] = $nonRelationPrimitive;

        [$hasManyArgs, $regularArgs] = ArgPartitioner::relationMethods(
            $argumentSet,
            new User(),
            HasMany::class,
        );

        $this->assertSame(
            ['nonRelationPrimitive' => $nonRelationPrimitive],
            $regularArgs->arguments,
        );

        $this->assertSame(
            [],
            $hasManyArgs->arguments,
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
            HasMany::class,
        );
    }

    public function testSaveAwareArgResolverWithNonModelRoot(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $saveAware = new Argument();
        $saveAware->directives->push(new SaveAwareNested());
        $argumentSet->arguments['saveAware'] = $saveAware;

        [$nestedArgs, $regularArgs] = ArgPartitioner::nestedArgResolvers($argumentSet, null);

        $this->assertSame(
            ['regular' => $regular],
            $regularArgs->arguments,
        );

        $this->assertSame(
            ['saveAware' => $saveAware],
            $nestedArgs->arguments,
            'SaveAwareArgResolver should be in nested (post-save) set when root is not a Model',
        );
    }

    public function testSaveAwareArgResolverWithModelRoot(): void
    {
        $argumentSet = new ArgumentSet();

        $regular = new Argument();
        $argumentSet->arguments['regular'] = $regular;

        $saveAware = new Argument();
        $saveAware->directives->push(new SaveAwareNested());
        $argumentSet->arguments['saveAware'] = $saveAware;

        [$nestedArgs, $regularArgs] = ArgPartitioner::nestedArgResolvers($argumentSet, new User());

        $this->assertSame(
            ['regular' => $regular, 'saveAware' => $saveAware],
            $regularArgs->arguments,
            'SaveAwareArgResolver with runBeforeSave=true should be excluded from nested set when root is Model',
        );

        $this->assertSame(
            [],
            $nestedArgs->arguments,
        );
    }
}
