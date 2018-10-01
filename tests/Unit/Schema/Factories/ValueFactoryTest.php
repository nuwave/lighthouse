<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Factories\ValueFactory;

class ValueFactoryTest extends TestCase
{
    /** @var ValueFactory */
    protected $valueFactory;

    public function setUp()
    {
        parent::setUp();

        $this->valueFactory = resolve(ValueFactory::class);
    }

    /**
     * @test
     */
    public function itCanSetNodeValueResolver()
    {
        $this->valueFactory->nodeResolver(function ($node) {
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
        $this->valueFactory->fieldResolver(function ($nodeValue, $fieldDefinition) {
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
