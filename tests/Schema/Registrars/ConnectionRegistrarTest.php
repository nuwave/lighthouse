<?php

namespace Nuwave\Lighthouse\Tests\Schema\Registrars;

use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\Registrars\TypeRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\EdgeRegistrar;
use Nuwave\Lighthouse\Schema\Registrars\ConnectionRegistrar;
use Nuwave\Lighthouse\Tests\TestCase;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Support\Definition\PageInfoType;
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
        $this->assertSame('UserConnection', $connectionType->name);
        $this->assertContains('pageInfo', array_keys($connectionType->config['fields']));
        $this->assertContains('edges', array_keys($connectionType->config['fields']));

        $edge = $this->edgeRegistrar->instance('user');
        $this->assertInstanceOf(ObjectType::class, $edge);
        $this->assertSame('UserEdge', $edge->name);
    }
}
