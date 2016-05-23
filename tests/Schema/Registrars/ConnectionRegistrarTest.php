<?php

namespace Nuwave\Relay\Tests\Schema\Registrars;

use Nuwave\Relay\Schema\SchemaBuilder;
use Nuwave\Relay\Schema\Registrars\TypeRegistrar;
use Nuwave\Relay\Schema\Registrars\EdgeRegistrar;
use Nuwave\Relay\Schema\Registrars\ConnectionRegistrar;
use Nuwave\Relay\Tests\TestCase;
use Nuwave\Relay\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Relay\Support\Definition\PageInfoType;
use GraphQL\Type\Definition\ObjectType;

class ConnectionRegistrarTest extends TestCase
{
    /**
     * Connection registrar.
     *
     * @var ConnectionRegistrar
     */
    protected $registrar;

    /**
     * Edge registrar.
     *
     * @var EdgeRegistrar
     */
    protected $edgeRegistrar;

    /**
     * Type Registrar.
     *
     * @var TypeRegistrar
     */
    protected $typeRegistrar;

    /**
     * Set up test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $schema = new SchemaBuilder;

        $this->registrar = new ConnectionRegistrar;
        $this->edgeRegistrar = new EdgeRegistrar;
        $this->typeRegistrar = new TypeRegistrar;

        $schema->setConnectionRegistrar($this->registrar);
        $schema->setEdgeRegistrar($this->edgeRegistrar);
        $schema->setTypeRegistrar($this->typeRegistrar);

        $this->registrar->setSchema($schema);
        $this->typeRegistrar->setSchema($schema);
        $this->edgeRegistrar->setSchema($schema);
    }

    /**
     * @test
     */
    public function itCanRetrieveConnectionInstanceFromRegistrar()
    {
        $this->typeRegistrar->register('user', UserType::class);
        $this->typeRegistrar->register('pageInfo', PageInfoType::class);

        $connectionField = $this->registrar->instance('user');
        $connectionType = $connectionField['type'];
        $this->assertInstanceof(ObjectType::class, $connectionType);
        $this->assertEquals('UserConnection', $connectionType->name);
        $this->assertContains('pageInfo', array_keys($connectionType->config['fields']));
        $this->assertContains('edges', array_keys($connectionType->config['fields']));

        $edge = $this->edgeRegistrar->instance('user');
        $this->assertInstanceOf(ObjectType::class, $edge);
        $this->assertEquals('UserEdge', $edge->name);
    }
}
