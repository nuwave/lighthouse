<?php

namespace Nuwave\Lighthouse\Tests\Schema\Registrars;

use GraphQL;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Schema\Registrars\TypeRegistrar;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Exceptions\GraphQLTypeInstanceNotFound;

class TypeRegistrarTest extends TestCase
{
    /**
     * Type registrar.
     *
     * @var TypeRegistrar
     */
    protected $registrar;

    /**
     * Set up test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $schema = new SchemaBuilder;

        $this->registrar = new TypeRegistrar;
        $this->registrar->setSchema($schema);
    }

    /**
     * @test
     */
    public function itCanRegisterType()
    {
        $field = $this->registrar->register('foo', 'FooClass');

        $this->assertCount(1, $this->registrar->all());
        $this->assertEquals('foo', $field->name);
        $this->assertEquals('FooClass', $field->namespace);
        $this->assertSame($field, $this->registrar->get('foo'));
    }

    /**
     * @test
     */
    public function itCanRetrieveTypeInstanceFromRegistrar()
    {
        $this->registrar->register('user', UserType::class);
        $instance = $this->registrar->instance('user');
        
        $this->assertInstanceOf(ObjectType::class, $instance);
        $this->assertEquals('User', $instance->name);
    }

    /**
     * @test
     */
    public function itCanUseFacadeToStoreType()
    {
        $field = GraphQL::schema()->type('user', UserType::class);
        $instance = GraphQL::type('user');

        $this->assertInstanceOf(ObjectType::class, $instance);
        $this->assertEquals('User', $instance->name);
    }

    /**
     * @test
     */
    public function itCanUseHelperToStoreTypeAndResolve()
    {
        $field = schema()->type('user', UserType::class);
        $instance = graphql()->type('user');

        $this->assertInstanceOf(ObjectType::class, $instance);
        $this->assertEquals('User', $instance->name);
    }

    /**
     * @test
     */
    public function itThrowsExceptionWhenUnregisteredTypeIsRequested()
    {
        $this->registrar->register('user', UserType::class);

        $this->setExpectedException(GraphQLTypeInstanceNotFound::class);

        $this->registrar->instance('foo');
    }
}
