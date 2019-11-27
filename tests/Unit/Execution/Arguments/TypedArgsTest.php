<?php

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\TypedArgs;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Tests\TestCase;

class TypedArgsTest extends TestCase
{
    public function testSimpleField(): void
    {
        $this->schema = '
        type Query {
            foo(bar: Int): Int
        }
        ';

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => 123,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertSame(123, $bar->value);
    }

    public function testNullableList(): void
    {
        $this->schema = '
        type Query {
            foo(bar: [Int!]): Int
        }
        ';

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => null,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertNull($bar->value);
    }

    public function testNullableInputObject(): void
    {
        $this->schema = '
        type Query {
            foo(bar: Bar): Int
        }
        
        input Bar {
            baz: ID
        }
        ';

        $argumentSet = $this->rootQueryArgumentSet([
            'bar' => null,
        ]);

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertNull($bar->value);
    }

    protected function rootQueryArgumentSet(array $args): ArgumentSet
    {
        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();
        /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArgs $typedArgs */
        $typedArgs = $this->app->make(TypedArgs::class);

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types['Query'];

        return $typedArgs->fromField(
            $args,
            ASTHelper::firstByName($queryType->fields, 'foo')
        );
    }
}
