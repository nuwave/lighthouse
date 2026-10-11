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
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        type Query {
            bar: String
        }
        GRAPHQL;

        Event::listen(ManipulateAST::class, static function (ManipulateAST $manipulateAST): void {
            $manipulateAST->documentAST->setTypeDefinition(
                Parser::objectTypeDefinition(self::PLACEHOLDER_QUERY),
            );
        });

        $this->graphQL(/** @lang GraphQL */ <<<'GRAPHQL'
        {
            foo
        }
        GRAPHQL)->assertJson([
            'data' => [
                'foo' => 42,
            ],
        ]);
    }
}
