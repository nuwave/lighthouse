<?php

namespace Tests\Unit\Events;

use GraphQL\Language\Parser;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateAST;
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
                Parser::objectTypeDefinition(self::PLACEHOLDER_QUERY)
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
