<?php

namespace Nuwave\Relay\Schema\Registrars;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Relay\Support\Definition\RelayConnectionType;
use Nuwave\Relay\Support\Definition\Fields\ConnectionField;

class ConnectionRegistrar extends BaseRegistrar
{
    /**
     * Collection of registered type instances.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $instances;

    /**
     * Create new instance of connection registrar.
     */
    public function __construct()
    {
        parent::__construct();

        $this->instances = collect();
    }

    /**
     * Add type to registrar.
     *
     * @param  string $name
     * @param  array  $field
     * @return array
     */
    public function register($name, $field)
    {
        $this->collection->put($name, $field);

        return $field;
    }

    /**
     * Get instance of connection type.
     *
     * @param  string $name
     * @param  string|null $parent
     * @param  boolean $fresh
     * @return \Nuwave\Relay\Support\Definition\Fields\ConnectionField
     */
    public function instance($name, $parent = null, $fresh = false)
    {
        if (! $fresh && $this->instances->has($name)) {
            return $this->instances->get($name);
        }

        $key = $parent ? $parent.'.'.$anme : $name;
        $nodeType = $this->getSchema()->typeInstance($name);
        $instance = $this->getInstance($name, $nodeType);

        $this->instances->put($key, $instance);

        return $instance;
    }

    /**
     * Generate connection field.
     *
     * @param  string $name
     * @param  ObjectType $nodeType
     * @return array
     */
    public function getInstance($name, ObjectType $nodeType)
    {
        $connection = new RelayConnectionType();

        $connectionName = (!preg_match('/Connection$/', $name)) ? $name.'Connection' : $name;
        $connection->setName(studly_case($connectionName));

        $pageInfoType = $this->getSchema()->typeInstance('pageInfo');
        $edgeType = $this->getSchema()->edgeInstance($name, $nodeType);

        $connection->setEdgeType($edgeType);
        $connection->setPageInfoType($pageInfoType);
        $instance = $connection->toType();

        $field = new ConnectionField([
            'args'    => RelayConnectionType::connectionArgs(),
            'type'    => $instance,
            'resolve' => null
        ]);

        if ($connection->interfaces) {
            InterfaceType::addImplementationToInterfaces($instance);
        }

        return $field;
    }
}
