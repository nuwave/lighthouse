<?php

namespace Tests\Unit\Events;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulatingAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class ManipulatingASTTest extends TestCase
{
    /**
     * @@test
     */
    public function itDispatchesManipulatingASTEvent(): void
    {
        $this->schema = $this->placeholderQuery();

        Event::fake([
            ManipulatingAST::class,
        ]);

        $this->query('
        {
            foo
        }
        ');

        Event::assertDispatched(ManipulatingAST::class);
    }

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

        Event::listen(ManipulatingAST::class, function (ManipulatingAST $manipulatingAST): void {
            $manipulatingAST->documentAST->setDefinition(
                PartialParser::objectTypeDefinition(
                    $this->placeholderQuery()
                )
            );
        });

        $this->query('
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
