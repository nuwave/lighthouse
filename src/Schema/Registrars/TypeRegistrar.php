<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Definition\EloquentType;
use Nuwave\Lighthouse\Support\Exceptions\GraphQLTypeInstanceNotFound;

class TypeRegistrar extends BaseRegistrar
{
    /**
     * Collection of registered type instances.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $instances;

    /**
     * Create new instance of type registrar.
     */
    public function __construct()
    {
        parent::__construct();

        $this->instances = collect();
    }

    /**
     * Get instance of type.
     *
     * @param  string $name
     * @param  boolean $fresh
     * @return \GraphQL\Type\Definition\ObjectType
     */
    public function instance($name, $fresh = false)
    {
        if (!$fresh && $this->instances->has($name)) {
            return $this->instances->get($name);
        }

        $type = $this->getType($name)->resolve();
        $instance = $type instanceof Model ? (new EloquentType($type, $name))->toType() : $type->toType();

        $this->instances->put($name, $instance);

        if ($type->interfaces) {
            InterfaceType::addImplementationToInterfaces($instance);
        }

        return $instance;
    }

    /**
     * Check if type is registered.
     *
     * @param  string $name
     * @return \Nuwave\Lighthouse\Schema\Field
     */
    protected function getType($name)
    {
        return $this->collection->get($name, function () use ($name) {
            throw new GraphQLTypeInstanceNotFound("Type [{$name}] not found.");
        });
    }
}
