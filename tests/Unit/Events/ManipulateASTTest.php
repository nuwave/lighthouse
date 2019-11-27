<?php

namespace Tests\Unit\Events;

use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Tests\TestCase;

class ManipulateASTTest extends TestCase
{
    public function testCanManipulateTheAST(): void
    {
        $this->schema = '
        type Query {
            bar: String
        }
        ';

        Event::listen(ManipulateAST::class, function (ManipulateAST $manipulateAST): void {
            $manipulateAST->documentAST->setTypeDefinition(
                PartialParser::objectTypeDefinition(self::PLACEHOLDER_QUERY)
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
