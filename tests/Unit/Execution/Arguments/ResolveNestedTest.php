<?php declare(strict_types=1);

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;
use Nuwave\Lighthouse\Schema\Directives\NestDirective;
use Tests\TestCase;
use Tests\Unit\Execution\Arguments\Fixtures\SaveAwareNested;
use Tests\Utils\Models\User;

final class ResolveNestedTest extends TestCase
{
    /** Savers that can not run pre-save arguments must still see their children resolved. */
    public function testPreSaveResolverInsideNestRunsWhenSaverIsNotPreSaveAware(): void
    {
        $saveAwareResolver = new SaveAwareNested();

        $preSaveChild = new Argument();
        $preSaveChild->value = new ArgumentSet();
        $preSaveChild->directives->push($saveAwareResolver);

        $nestValue = new ArgumentSet();
        $nestValue->arguments['location'] = $preSaveChild;

        $nest = new Argument();
        $nest->value = $nestValue;
        $nest->directives->push(new NestDirective());

        $name = new Argument();
        $name->value = 'Sepp';

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['name'] = $name;
        $argumentSet->arguments['nested'] = $nest;

        $model = new User();
        $passedToSaver = null;
        $resolveNested = new ResolveNested(static function (mixed $root, ArgumentSet $args) use (&$passedToSaver): mixed {
            $passedToSaver = $args;

            return $root;
        });

        $resolveNested($model, $argumentSet);

        $this->assertTrue($saveAwareResolver->wasCalled, 'Pre-save resolvers inside @nest must run even when the saver cannot run them before save');
        $this->assertSame($model, $saveAwareResolver->receivedRoot);

        $this->assertInstanceOf(ArgumentSet::class, $passedToSaver);
        $this->assertSame(
            ['name' => $name],
            $passedToSaver->arguments,
            'Savers that cannot run pre-save arguments must not receive them',
        );
    }
}
