<?php

namespace Tests\Unit\Schema\Directives\Client;

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
        $resolver = addslashes(self::class).'@resolve';
        $this->schema = '
        directive @filter(key: String = "default value") on FIELD
        
        type Query {
            foo: String @field(resolver: "'.$resolver.'")
        }
        ';

        $this->query('
        {
            foo @filter(key: "baz")
        }
        ')->assertJson([
            'data' => [
                'foo' => 'baz'
            ]
        ]);
    }

    public function resolve($root, array $args, GraphQLContext $context, ResolveInfo $info): string
    {
        /** @var ArgumentNode $key */
        $key = collect($info->fieldNodes)
            ->flatMap(function (FieldNode $node): Collection {
                return collect($node->directives);
            })
            ->filter(function (DirectiveNode $directive): bool {
                return $directive->name->value === 'filter';
            })
            ->flatMap(function (DirectiveNode $directive): Collection {
                return collect($directive->arguments);
            })
            ->first(function (ArgumentNode $arg): bool {
                return $arg->name->value === 'key';
            });

        return $key->value->value;
    }
}
