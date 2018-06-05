<?php

namespace Tests\Unit\Schema\Directives\Client;

use GraphQL\Type\Definition\ResolveInfo;
use Tests\TestCase;

class ClientDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanDefineAClientDirective()
    {
        $resolver = addslashes(self::class).'@resolve';
        $schema = '
        directive @filter(key: String = "default value") on FIELD
        type Query {
            foo: String @field(resolver: "'.$resolver.'")
        }';

        $result = $this->execute($schema, '{ foo @filter(key: "baz") }');
        $this->assertEquals(['foo' => 'baz'], $result->data);
    }

    public function resolve($root, array $args, $context, ResolveInfo $info)
    {
        $key = collect($info->fieldNodes)->flatMap(function ($node) {
            return collect($node->directives);
        })->filter(function ($directive) {
            return 'filter' == $directive->name->value;
        })->flatMap(function ($directive) {
            return collect($directive->arguments);
        })->filter(function ($arg) {
            return 'key' == $arg->name->value;
        })->first();

        return $key->value->value;
    }
}
