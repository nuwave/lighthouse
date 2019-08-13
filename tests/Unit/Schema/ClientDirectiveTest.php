<?php

namespace Tests\Unit\Schema;

use Tests\TestCase;
use Illuminate\Support\Arr;
use GraphQL\Type\Definition\Directive;
use Tests\Integration\IntrospectionTest;

class ClientDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itReturnsDefaultDirectivesInIntrospection(): void
    {
        $this->schema = $this->placeholderQuery();

        $directives = $this->introspectDirectives();

        $this->assertNotNull(
            Arr::first($directives, $this->makeDirectiveNameMatcher(Directive::SKIP_NAME))
        );
        $this->assertNotNull(
            Arr::first($directives, $this->makeDirectiveNameMatcher(Directive::INCLUDE_NAME))
        );
    }

    /**
     * @test
     */
    public function itCanDefineACustomClientDirective(): void
    {
        $this->schema = '
        "foo"
        directive @bar(
            "foobar"
            baz: String = "barbaz"
        ) on FIELD
        '.$this->placeholderQuery();

        $directives = $this->introspectDirectives();

        $bar = Arr::first($directives, $this->makeDirectiveNameMatcher('bar'));

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

    protected function makeDirectiveNameMatcher(string $name): \Closure
    {
        return function (array $directive) use ($name): bool {
            return $directive['name'] === $name;
        };
    }

    /**
     * @return array[]
     */
    public function introspectDirectives(): array
    {
        $introspection = $this->graphQL(IntrospectionTest::INTROSPECTION_QUERY);

        return $introspection->jsonGet('data.__schema.directives');
    }
}
