<?php

namespace Tests\Unit\Execution\Arguments;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\StringValueNode;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
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
        $generateRenameDirective = function ($newName) {
            $node = new DirectiveDefinitionNode([]);

            $node->directives = [
                new DirectiveNode([
                    'name' => new NameNode(['value' => 'rename']),
                    'arguments' => [
                        new ArgumentNode([
                            'name' => new NameNode(['value' => 'attribute']),
                            'value' => new StringValueNode(['value' => $newName]),
                        ]),
                    ],
                ]),
            ];

            $rename = new RenameDirective();

            return $rename->hydrate($node);
        };

        $firstName = new Argument();
        $firstName->value = 'Michael';
        $firstName->directives = collect([$generateRenameDirective('first_name')]);

        $lastName = new Argument();
        $lastName->value = 'Jordan';
        $lastName->directives = collect([$generateRenameDirective('last_name')]);

        $postName = new Argument();
        $postName->value = 'Hello World';
        $postName->directives = collect([$generateRenameDirective('title')]);

        $postSet = new ArgumentSet();
        $postSet->arguments['post_title'] = $postName;

        $postArg = new Argument();
        $postArg->value = $postSet;

        $userSet = new ArgumentSet();
        $userSet->arguments = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'post' => $postArg,
        ];

        $renamedSet = $userSet->rename();

        $this->assertSame([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'post' => $postArg,
        ], $renamedSet->arguments);

        $this->assertSame([
            'title' => $postName,
        ], $renamedSet->arguments['post']->value->arguments);
    }
}
