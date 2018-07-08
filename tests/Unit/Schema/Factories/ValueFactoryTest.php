<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;

class ValueFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itCanSetNodeValueResolver()
    {
        graphql()->values()->nodeResolver(function ($node) {
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

        $result = $this->execute('
            type Query {
                bar: String
            }',
            '{ foo }'
        );

        $this->assertEquals(['foo' => 'bar'], $result->data);
    }

    /**
     * @test
     */
    public function itCanSetFieldValueResolver()
    {
        graphql()->values()->fieldResolver(function ($nodeValue, $fieldDefinition) {
            return new class($nodeValue, $fieldDefinition) extends FieldValue {
                public function getResolver(): \Closure
                {
                    return function () {
                        return 'foobar';
                    };
                }
            };
        });

        $result = $this->execute('
            type Query {
                foo: String
            }',
            '{ foo }'
        );

        $this->assertEquals(['foo' => 'foobar'], $result->data);
    }
}
