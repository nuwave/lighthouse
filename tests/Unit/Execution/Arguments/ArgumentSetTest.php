<?php

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\SpreadDirective;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Tests\TestCase;

class ArgumentSetTest extends TestCase
{
    public function testSpreadsNestedInput(): void
    {
        $spreadDirective = new SpreadDirective();
        $directiveCollection = collect([$spreadDirective]);

        // Those are the leave values we want in the spread result
        $bazValue = 2;
        $baz = new Argument();
        $baz->value = $bazValue;

        $foo = new Argument();
        $fooValue = 1;
        $foo->value = $fooValue;

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
        /** @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory $directiveFactory */
        $directiveFactory = app(DirectiveFactory::class);

        $renameDirective = PartialParser::directive('@rename');
        $renameDirective->directives = collect([PartialParser::directive('@rename(attribute: "first_name")')]);
        $renameDirective = $directiveFactory->create('rename', $renameDirective);

        $firstName = new Argument();
        $firstName->value = 'Michael';
        $firstName->directives = collect([$renameDirective]);

        $argumentSet = new ArgumentSet();
        $argumentSet->arguments = [
            'firstName' => $firstName,
        ];

        $renamedSet = $argumentSet->rename();

        $this->assertSame([
            'first_name' => $firstName,
        ], $renamedSet->arguments);
    }
}
