<?php

namespace Tests\Unit\Schema;

use GraphQL\Type\Definition\Directive;
use Tests\TestCase;

class ClientDirectiveTest extends TestCase
{
    public function testReturnsDefaultDirectivesInIntrospection(): void
    {
        $this->assertNotNull(
            $this->introspectDirective(Directive::SKIP_NAME)
        );
        $this->assertNotNull(
            $this->introspectDirective(Directive::INCLUDE_NAME)
        );
    }

    public function testCanDefineACustomClientDirective(): void
    {
        $this->schema .= /** @lang GraphQL */ '
        "foo"
        directive @bar(
            "foobar"
            baz: String = "barbaz"
        ) on FIELD
        ';

        $bar = $this->introspectDirective('bar');

        $this->assertSame(
            [
                'name' => 'bar',
                'description' => 'foo',
                'args' => [
                    [
                        'name' => 'baz',
                        'description' => 'foobar',
                        'type' => [
                            'kind' => 'SCALAR',
                            'name' => 'String',
                            'ofType' => null,
                        ],
                        'defaultValue' => '"barbaz"',
                    ],
                ],
                'locations' => [
                    'FIELD',
                ],
            ],
            $bar
        );
    }
}
