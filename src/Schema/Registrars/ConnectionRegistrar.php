<?php

namespace Nuwave\Relay\Schema\Registrars;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Relay\Support\Definition\RelayConnectionType;

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
     * Get instance of connection type.
     *
     * @param  string $name
     * @param  Closure|null $resolve
     * @param  boolean $fresh
     * @return array
     */
    public function instance($name, $resolve = null, $fresh = false)
    {
        // TODO: Ensure type ($name) exists in TypeRegistrar.
        // TODO: Create resolve function for connection if is_callable($resolve)

        if (! $fresh && $this->instances->has($name)) {
            $field = $this->instances->get($name);
            $field['resolve'] = $resolve;

            return $field;
        }

        $instance = $this->getInstance($name, $resolve);

        $this->instances->put($name, $instance);

        return $instance;
    }

    /**
     * Generate connection field.
     *
     * @param  string $name
     * @param  Closure|null $resolve
     * @return array
     */
    public function getInstance($name, $resolve = null)
    {
        $connection = new RelayConnectionType();

        $connectionName = (!preg_match('/Connection$/', $name)) ? $name.'Connection' : $name;
        $connection->setName(studly_case($connectionName));

        $nodeType = $this->getSchema()->typeInstance($name);
        $pageInfoType = $this->getSchema()->typeInstance('pageInfo');
        $edgeType = $this->getSchema()->edgeInstance($name, $nodeType);

        $connection->setEdgeType($edgeType);
        $connection->setPageInfoType($pageInfoType);
        $instance = $connection->toType();

        $field = [
            'args'    => RelayConnectionType::connectionArgs(),
            'type'    => $instance,
            'resolve' => $resolve
        ];

        if ($connection->interfaces) {
            InterfaceType::addImplementationToInterfaces($instance);
        }

        return $field;
    }
}
