<?php

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\RenameDirective;
use Nuwave\Lighthouse\Schema\Directives\SpreadDirective;
use Tests\TestCase;

class ArgumentSetTest extends TestCase
{
    public function testSpreadsNestedInput(): void
    {
        $spreadDirective = new SpreadDirective();
        $directiveCollection = collect([$spreadDirective]);

        // Those are the leave values we want in the spread result
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $baz = new Argument();
        $bazValue = 2;
        $baz->value = $bazValue;

        $barInput = new ArgumentSet();
        $barInput->arguments['baz'] = $baz;

        $barArgument = new Argument();
        $barArgument->directives = $directiveCollection;
        $barArgument->value = $barInput;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;
        $fooInput->arguments['bar'] = $barArgument;

        $inputArgument = new Argument();
        $inputArgument->directives = $directiveCollection;
        $inputArgument->value = $fooInput;

        $argumentSet = new ArgumentSet();
        $argumentSet->directives = $directiveCollection;
        $argumentSet->arguments['input'] = $inputArgument;

        $spreadArgumentSet = $argumentSet->spread();
        $spreadArguments = $spreadArgumentSet->arguments;

        $this->assertSame($spreadArguments['foo']->value, $fooValue);
        $this->assertSame($spreadArguments['baz']->value, $bazValue);
    }

    public function testSingleFieldToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['foo'] = $foo;

        $this->assertSame(
            [
                'foo' => $fooValue,
            ],
            $argumentSet->toArray()
        );
    }

    public function testInputObjectToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;

        $inputArgument = new Argument();
        $inputArgument->value = $fooInput;

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['input'] = $inputArgument;

        $this->assertSame(
            [
                'input' => [
                    'foo' => $fooValue,
                ],
            ],
            $argumentSet->toArray()
        );
    }

    public function testListOfInputObjectsToArray(): void
    {
        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

        $fooInput = new ArgumentSet();
        $fooInput->arguments['foo'] = $foo;

        $inputArgument = new Argument();
        $inputArgument->value = [$fooInput, $fooInput];

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments['input'] = $inputArgument;

        $this->assertSame(
            [
                'input' => [
                    [
                        'foo' => $fooValue,
                    ],
                    [
                        'foo' => $fooValue,
                    ],
                ],
            ],
            $argumentSet->toArray()
        );
    }

    public function testRenameInput(): void
    {
        $firstName = new Argument();
        $firstName->value = 'Michael';
        $firstName->directives = collect([$this->makeRenameDirective('first_name')]);

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments = [
            'firstName' => $firstName,
        ];

        $renamedSet = $argumentSet->rename();

        $this->assertSame(
            [
                'first_name' => $firstName,
            ],
            $renamedSet->arguments
        );
    }

    public function testRenameNested(): void
    {
        $secondLevelArg = new Argument();
        $secondLevelArg->value = 'Michael';
        $secondLevelArg->directives = collect([$this->makeRenameDirective('second_internal')]);

        $secondLevelSet = new ArgumentSet();
        $secondLevelSet->arguments = [
            'secondExternal' => $secondLevelArg,
        ];

        $firstLevelArg = new Argument();
        $firstLevelArg->value = $secondLevelSet;
        $firstLevelArg->directives = collect([$this->makeRenameDirective('first_internal')]);

        $firstLevelSet = new ArgumentSet();
        $firstLevelSet->arguments = [
            'firstExternal' => $firstLevelArg,
        ];

        $renamedFirstLevel = $firstLevelSet->rename();

        $renamedSecondLevel = $renamedFirstLevel->arguments['first_internal']->value;
        $this->assertSame(
            [
                'second_internal' => $secondLevelArg,
            ],
            $renamedSecondLevel->arguments
        );
    }

    protected function makeRenameDirective(string $attribute): RenameDirective
    {
        $renameDirective = new RenameDirective();
        $renameDirective->hydrate(
            // We require some placeholder for the directive definition to sit on
            PartialParser::fieldDefinition(/* @lang GraphQL */ <<<GRAPHQL
placeholder: ID @rename(attribute: "$attribute")
GRAPHQL
            )
        );

        return $renameDirective;
    }
}
