<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

use ReflectionClass;
use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ObjectType;
use Nuwave\Lighthouse\Support\Traits\GlobalIdTrait;
use Nuwave\Lighthouse\Support\Interfaces\ConnectionEdge;
use Nuwave\Lighthouse\Schema\Generators\EdgeTypeGenerator;
use Nuwave\Lighthouse\Support\Definition\Fields\EdgeField;

class EdgeRegistrar extends BaseRegistrar
{
    use GlobalIdTrait;

    /**
     * Collection of registered type instances.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $instances;

    /**
     * EdgeType generator.
     *
     * @var \Nuwave\Lighthouse\Schema\Generators\EdgeTypeGenerator
     */
    protected $generator;

    /**
     * Create new instance of type registrar.
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->instances = new Collection;
    }

    /**
     * Get instance of edge type.
     *
     * @param  string  $name
     * @param  bool  $fresh
     * @param  ObjectType|null  $type
     * @return \GraphQL\Type\Definition\ObjectType|null
     */
    public function instance($name, $fresh = false, ObjectType $type = null)
    {
        $instanceName = $this->instanceName($name);

        if (! $fresh && $this->instances->has($instanceName)) {
            return $this->instances->get($instanceName);
        }

        if ($name instanceof ConnectionEdge) {
            $instance = $this->createEdge($name);
            $this->instances->put($instanceName, $instance);

            return $instance;
        } elseif ($type) {
            $instance = $this->createInstance($name, $type);

            $this->instances->put($name, $instance);

            return $instance;
        }
    }

    /**
     * Store new instance of edge.
     *
     * @param  string  $name
     * @param  \GraphQL\Type\Definition\ObjectType  $type
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public function createInstance($name, $type)
    {
        return $this->getGenerator()->build($name, $type);
    }

    /**
     * Create edge instance.
     *
     * @param  \Nuwave\Lighthouse\Support\Interfaces\ConnectionEdge  $edge
     * @return EdgeField
     */
    protected function createEdge(ConnectionEdge $edge)
    {
        $graphqlType = app('graphql')->type($edge->type());

        return new EdgeField([
            'type' => $this->createInstance($edge->name(), $graphqlType),
            'resolve' => function ($payload) use ($edge) {
                $model = $edge->edge($payload);
                $cursor = call_user_func_array([$edge, 'cursor'], [$payload]);
                $model->relayCursor = $this->encodeGlobalId('arrayconnection', $cursor);

                return $model;
            },
        ]);
    }

    /**
     * Set local instance of generator.
     *
     * @param  \Nuwave\Lighthouse\Schema\Generators\EdgeTypeGenerator  $generator
     * @return void
     */
    public function setGenerator(EdgeTypeGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Get instance of edge generator.
     *
     * @return \Nuwave\Lighthouse\Schema\Generators\EdgeTypeGenerator
     */
    public function getGenerator()
    {
        return $this->generator ?: app(EdgeTypeGenerator::class);
    }

    /**
     * Get instance name.
     *
     * @param  mixed  $name
     * @return string
     */
    protected function instanceName($name)
    {
        if ($name instanceof ConnectionEdge) {
            $class = (new ReflectionClass($name))->getName();

            return strtolower(snake_case((str_replace('\\', '_', $class))));
        }

        return $name;
    }
}
