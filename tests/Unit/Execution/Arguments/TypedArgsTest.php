<?php

namespace Tests\Unit\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Tests\TestCase;
use Nuwave\Lighthouse\Execution\Arguments\TypedArgs;

class TypedArgsTest extends TestCase
{
    public function testSimpleField(): void
    {
        $this->schema = '
        type Query {
            foo(bar: Int): Int
        }
        ';

        /** @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder $astBuilder */
        $astBuilder = $this->app->make(ASTBuilder::class);
        $documentAST = $astBuilder->documentAST();
        /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArgs $typedArgs */
        $typedArgs = $this->app->make(TypedArgs::class);

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $queryType */
        $queryType = $documentAST->types['Query'];

        $argumentSet = $typedArgs->fromField(
            [
                'bar' => 123,
            ],
            ASTHelper::firstByName($queryType->fields, 'foo')
        );

        $this->assertCount(1, $argumentSet->arguments);

        $bar = $argumentSet->arguments['bar'];
        $this->assertInstanceOf(Argument::class, $bar);
        $this->assertSame(123, $bar->value);
    }
}
