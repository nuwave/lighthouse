<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use GraphQL\Language\Parser;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Tests\TestCase;

final class ManipulateASTTest extends TestCase
{
    public function testManipulateTheAST(): void
    {
        $this->schema = '
        type Query {
            bar: String
        }
        ';

        Event::listen(ManipulateAST::class, static function (ManipulateAST $manipulateAST): void {
            $manipulateAST->documentAST->setTypeDefinition(
                Parser::objectTypeDefinition(self::PLACEHOLDER_QUERY),
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
