<?php

namespace Tests\Unit\Events;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class ManipulateASTTest extends TestCase
{
    /**
     * @test
     */
    public function itCanManipulateTheAST(): void
    {
        $this->schema = '
        type Query {
            bar: String
        }
        ';

        Event::listen(ManipulateAST::class, function (ManipulateAST $manipulateAST): void {
            $manipulateAST->documentAST->setTypeDefinition(
                PartialParser::objectTypeDefinition(
                    $this->placeholderQuery()
                )
            );
        });

        $this->graphQL('
        {
            foo
        }
        ')->assertJson([
            'data' => [
                'foo' => 42,
            ],
        ]);
    }
}
