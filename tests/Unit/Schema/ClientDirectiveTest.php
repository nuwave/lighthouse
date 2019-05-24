<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ClientDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanDefineAClientDirective(): void
    {
        $this->schema = '
        directive @filter(key: String = "default value") on FIELD
        
        type Query {
            foo: String @field(resolver: "'.$this->qualifyTestResolver().'")
        }
        ';

        $this->graphQL('
        {
            foo @filter(key: "baz")
        }
        ')->assertJson([
            'data' => [
                'foo' => 'baz',
            ],
        ]);
    }

    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): string
    {
        /** @var \GraphQL\Language\AST\ArgumentNode $key */
        $key = (new Collection($resolveInfo->fieldNodes))
            ->flatMap(function (FieldNode $node): Collection {
                return new Collection($node->directives);
            })
            ->filter(function (DirectiveNode $directive): bool {
                return $directive->name->value === 'filter';
            })
            ->flatMap(function (DirectiveNode $directive): Collection {
                return new Collection($directive->arguments);
            })
            ->first(function (ArgumentNode $arg): bool {
                return $arg->name->value === 'key';
            });

        return $key->value->value;
    }
}
