<?php

namespace Tests\Unit\Schema\Factories;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Factories\ValueFactory;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Tests\TestCase;

class ValueFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itCanSetNodeValueResolver()
    {
        $this->app->get(ValueFactory::class)->nodeResolver(function ($node) {
            return new class($node) extends NodeValue {
                public function getType()
                {
                    return new ObjectType([
                        'name' => $this->getNodeName(),
                        'fields' => [
                            'foo' => [
                                'type' => Type::string(),
                                'resolve' => function () {
                                    return 'bar';
                                },
                            ],
                        ],
                    ]);
                }
            };
        });

        $schema = '
        type Query {
            foo: String
        }
        ';
        $query = '
        {
            foo
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals(['foo' => 'bar'], $result['data']);
    }

    /**
     * @test
     */
    public function itCanSetFieldValueResolver()
    {
        $this->app->get(ValueFactory::class)->fieldResolver(function ($nodeValue, $fieldDefinition) {
            return new class($nodeValue, $fieldDefinition) extends FieldValue {
                public function getResolver(): \Closure
                {
                    return function () {
                        return 'foobar';
                    };
                }
            };
        });

        $schema = '
        type Query {
            foo: String
        }
        ';
        $query = '
        {
            foo
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertEquals(['foo' => 'foobar'], $result['data']);
    }
}
