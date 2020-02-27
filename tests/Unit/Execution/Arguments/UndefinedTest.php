<?php

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\Undefined;
use Tests\TestCase;

class UndefinedTest extends TestCase
{
    public function testRemoveUndefinedInNestedObject(): void
    {
        $secondLevelGiven = new Argument();
        $secondLevelGiven->value = 'Michael';

        $secondLevelUndefined = new Argument();
        $secondLevelUndefined->value = Undefined::undefined();

        $secondLevelSet = new ArgumentSet();
        $secondLevelSet->arguments = [
            'given' => $secondLevelGiven,
            'undefined' => $secondLevelUndefined,
        ];

        $firstLevelArg = new Argument();
        $firstLevelArg->value = $secondLevelSet;

        $firstLevelUndefined = new Argument();
        $firstLevelUndefined->value = Undefined::undefined();

        $firstLevelSet = new ArgumentSet();
        $firstLevelSet->arguments = [
            'nested' => $firstLevelArg,
            'undefined' => $firstLevelUndefined,
        ];

        $withoutUndefined = Undefined::removeUndefined($firstLevelSet);

        $this->assertArrayNotHasKey('undefined', $withoutUndefined->arguments);

        $secondLevelWithout = $withoutUndefined->arguments['nested']->value;
        $this->assertArrayNotHasKey('undefined', $secondLevelWithout->arguments);
    }

    public function testRemoveUndefinedInList(): void
    {
        $given = new Argument();
        $given->value = 'Michael';

        $undefined = new Argument();
        $undefined->value = Undefined::undefined();

        $secondLevelSet = new ArgumentSet();
        $secondLevelSet->arguments = [
            'given' => $given,
            'undefined' => $undefined,
        ];

        $listOfObject = new Argument();
        $listOfObject->value = [$secondLevelSet, $secondLevelSet];

        $set = new ArgumentSet();
        $set->arguments = [
            'list' => $listOfObject,
        ];

        $withoutUndefined = Undefined::removeUndefined($set);
        $secondLevelWithout = $withoutUndefined->arguments['list']->value;

        $this->assertArrayNotHasKey('undefined', $secondLevelWithout[0]->arguments);
        $this->assertArrayNotHasKey('undefined', $secondLevelWithout[1]->arguments);
    }
}
