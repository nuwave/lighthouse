<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

use Nuwave\Lighthouse\Schema\Registrars\BaseRegistrar;
use Nuwave\Lighthouse\Schema\Generators\EdgeTypeGenerator;
use GraphQL\Type\Definition\ObjectType;

class EdgeRegistrar extends BaseRegistrar
{
    /**
     * Collection of registered type instances.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $instances;

    /**
     * EdgeType generator.
     *
     * @var EdgeTypeGenerator
     */
    protected $generator;

    /**
     * Create new instance of type registrar.
     */
    public function __construct()
    {
        parent::__construct();

        $this->instances = collect();
    }

    /**
     * Get instance of edge type.
     *
     * @param  string $name
     * @param  boolean $fresh
     * @param  ObjectType|null $type
     * @return \GraphQL\Type\Definition\ObjectType|null
     */
    public function instance($name, $fresh = false, ObjectType $type = null)
    {
        if (! $fresh && $this->instances->has($name)) {
            return $this->instances->get($name);
        }

        if ($type) {
            $instance = $this->createInstance($name, $type);

            $this->instances->put($name, $instance);

            return $instance;
        }

        return null;
    }

    /**
     * Store new instance of edge.
     *
     * @param  string $name
     * @param  ObjectType $type
     * @return ObjectType
     */
    public function createInstance($name, $type)
    {
        return $this->getGenerator()->build($name, $type);
    }

    /**
     * Set local instance of generator.
     *
     * @param EdgeTypeGenerator $generator
     */
    public function setGenerator(EdgeTypeGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get instance of edge generator.
     *
     * @return EdgeTypeGenerator
     */
    public function getGenerator()
    {
        return $this->generator ?: app(EdgeTypeGenerator::class);
    }
}
