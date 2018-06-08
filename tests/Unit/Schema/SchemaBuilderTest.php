<?php

namespace Tests\Unit\Schema;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Schema;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Tests\TestCase;

class SchemaBuilderTest extends TestCase
{
    /**
     * Get test environment setup.
     *
     * @param mixed $app
     */
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set(
            'lighthouse.namespaces.queries',
            'Tests\\Utils\\Queries'
        );

        $app['config']->set(
            'lighthouse.namespaces.mutations',
            'Tests\\Utils\\Mutations'
        );
    }

    /**
     * @param $schema
     *
     * @return Collection
     */
    protected function getTypesFromString($schema)
    {
        $ast = ASTBuilder::generate($schema);

        return (new SchemaBuilder())->convertTypes($ast);
    }

    /**
     * @test
     */
    public function itCanResolveEnumTypes()
    {
        $schema = '
        enum Role {
            # Company administrator.
            admin @enum(value:"admin")

            # Company employee.
            employee @enum(value:"employee")
        }
        ';

        $types = $this->getTypesFromString($schema);
        $this->assertInstanceOf(EnumType::class, $types->firstWhere('name', 'Role'));
    }

    /**
     * @test
     */
    public function itCanResolveInterfaceTypes()
    {
        $schema = '
        interface Foo {
            # bar is baz
            bar: String!
        }
        ';

        $types = $this->getTypesFromString($schema);
        $this->assertInstanceOf(InterfaceType::class, $types->firstWhere('name', 'Foo'));
    }

    /**
     * @test
     */
    public function itCanResolveScalarTypes()
    {
        $schema = '
        scalar DateTime @scalar(class:"DateTime")
        ';

        $this->app['config']->set('lighthouse.namespaces.scalars', 'Nuwave\Lighthouse\Schema\Types\Scalars');
        $types = $this->getTypesFromString($schema);
        $this->assertInstanceOf(ScalarType::class, $types->firstWhere('name', 'DateTime'));
    }

    /**
     * @test
     */
    public function itCanResolveObjectTypes()
    {
        $schema = '
        type Foo {
            # bar attribute of Foo
            bar: String!
        }
        ';

        $types = $this->getTypesFromString($schema);
        $this->assertInstanceOf(ObjectType::class, $types->firstWhere('name', 'Foo'));

        $config = $types->firstWhere('name', 'Foo')->config;
        $this->assertEquals('Foo', data_get($config, 'name'));
        $this->assertEquals('bar attribute of Foo', array_get($config['fields'](), 'bar.description'));
    }

    /**
     * @test
     */
    public function itCanResolveInputObjectTypes()
    {
        $schema = '
        input CreateFoo {
            foo: String!
            bar: Int
        }
        ';

        $types = $this->getTypesFromString($schema);
        $this->assertInstanceOf(InputType::class, $types->firstWhere('name', 'CreateFoo'));

        $config = $types->firstWhere('name', 'CreateFoo')->config;
        $fields = $config['fields']();
        $this->assertEquals('CreateFoo', data_get($config, 'name'));
        $this->assertArrayHasKey('foo', $fields);
        $this->assertArrayHasKey('bar', $fields);
    }

    /**
     * @test
     */
    public function itCanResolveMutations()
    {
        $schema = '
        type Mutation {
            foo(bar: String! baz: String): String
        }
        ';

        $type = $this->getTypesFromString($schema)->firstWhere('name', 'Mutation');
        $mutation = $type->config['fields']()['foo'];

        $this->assertArrayHasKey('args', $mutation);
        $this->assertArrayHasKey('type', $mutation);
        $this->assertArrayHasKey('resolve', $mutation);
        $this->assertArrayHasKey('bar', $mutation['args']);
        $this->assertArrayHasKey('baz', $mutation['args']);
    }

    /**
     * @test
     */
    public function itCanResolveQueries()
    {
        $schema = '
        type Query {
            foo(bar: String! baz: String): String
        }
        ';

        $type = $this->getTypesFromString($schema)->firstWhere('name', 'Query');
        $query = $type->config['fields']()['foo'];

        $this->assertArrayHasKey('args', $query);
        $this->assertArrayHasKey('type', $query);
        $this->assertArrayHasKey('resolve', $query);
        $this->assertArrayHasKey('bar', $query['args']);
        $this->assertArrayHasKey('baz', $query['args']);
    }

    /**
     * @test
     */
    public function itCanExtendObjectTypes()
    {
        $schema = '
        type Foo {
            bar: String!
        }
        extend type Foo {
            baz: String!
        }
        ';

        $type = $this->getTypesFromString($schema)->first();
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('baz', $fields);
    }

    /**
     * @test
     */
    public function itCanExtendQuery()
    {
        $schema = '
        type Query {
            foo: String!
        }
        extend type Query {
            bar: String!
        }
        ';

        $type = $this->getTypesFromString($schema)->firstWhere('name', 'Query');
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('bar', $fields);
    }

    /**
     * @test
     */
    public function itCanExtendMutation()
    {
        $schema = '
        type Mutation {
            foo: String!
        }
        extend type Mutation {
            bar: String!
        }
        ';

        $type = $this->getTypesFromString($schema)->firstWhere('name', 'Mutation');
        $fields = $type->config['fields']();
        $this->assertArrayHasKey('bar', $fields);
    }

    /**
     * @test
     */
    public function itCanGenerateValidGraphQLSchema()
    {
        $schema = $this->buildSchemaFromString('
        type Query {
            foo: String!
        }
        type Mutation {
            foo: String!
        }
        ');

        $this->assertInstanceOf(Schema::class, $schema);
        // This would throw if the schema were invalid
        $schema->assertValid();
    }
}
