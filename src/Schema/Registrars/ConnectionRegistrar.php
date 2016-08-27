<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Support\Definition\RelayConnectionType;
use Nuwave\Lighthouse\Support\Definition\Fields\ConnectionField;
use Nuwave\Lighthouse\Support\Interfaces\Connection;

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
     * @param  bool $fresh
     * @return \Nuwave\Lighthouse\Support\Definition\Fields\ConnectionField
     */
    public function instance($name, $parent = null, $fresh = false)
    {
        $typeName = $this->getName($name);

        if (! $fresh && $this->instances->has($typeName)) {
            return $this->instances->get($name);
        }

        $key = $parent ? $parent.'.'.$typeName : $typeName;
        $nodeType = $this->getSchema()->typeInstance($typeName);
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
        $isConnection = $name instanceof Connection;
        $connection = new RelayConnectionType();
        $typeName = $this->getName($name);
        $connectionName = (!preg_match('/Connection$/', $typeName)) ? $typeName.'Connection' : $typeName;
        $connection->setName(studly_case($connectionName));

        $pageInfoType = $this->getSchema()->typeInstance('pageInfo');
        $edgeType = $this->getSchema()->edgeInstance($typeName, $nodeType);

        $connection->setEdgeType($edgeType);
        $connection->setPageInfoType($pageInfoType);
        $instance = $connection->toType();

        $field = new ConnectionField([
            'args'    => $isConnection ? array_merge($name->args(), RelayConnectionType::connectionArgs()) : RelayConnectionType::connectionArgs(),
            'type'    => $instance,
            'resolve' => $isConnection ? array($name, 'resolve') : null
        ]);

        if ($connection->interfaces) {
            InterfaceType::addImplementationToInterfaces($instance);
        }

        return $field;
    }

    /**
     * Extract name.
     *
     * @param  mixed $name
     * @return string
     */
    protected function getName($name)
    {
        if ($name instanceof Connection) {
            return $name->type();
        }

        return $name;
    }
}
