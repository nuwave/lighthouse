<?php

namespace Nuwave\Lighthouse\Support\Schema;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Database\ModelIdentifier;
use ReflectionClass;
use ReflectionProperty;

abstract class GraphQLSubscription implements ShouldBroadcast
{
     use SerializesAndRestoresModelIdentifiers;

    /**
     * Root object.
     *
     * @var mixed
     */
    protected $obj;

    /**
     * Field arguments.
     *
     * @var array
     */
    protected $args = [];

    /**
     * Query context.
     *
     * @var Context
     */
    protected $context;

    /**
     * Field resolve info.
     *
     * @var ResolveInfo
     */
    protected $info;

    /**
     * Resolve the mutation.
     *
     * @param mixed       $obj
     * @param array       $args
     * @param Context     $context
     * @param ResolveInfo $info
     * @return mixed
     */
    public function fillAndResolve($obj, $args = null, $context = null, $info = null)
    {
        $this->obj = $obj;
        $this->args = $args;
        $this->context = $context;
        $this->info = $info;
        return $this->resolve();
    }

    /**
     * Resolve the mutation.
     *
     * @return mixed
     */
    abstract public function resolve();

    /**
     * Prepare the instance for serialization.
     *
     * @return array
     */
    public function __sleep()
    {
        $properties = (new ReflectionClass($this))->getProperties();

        foreach ($properties as $property) {
            $property->setValue($this, $this->getSerializedPropertyValue(
                $this->getPropertyValue($property)
            ));
        }

        return array_values(array_filter(array_map(function ($p) {
            return $p->isStatic() ? null : $p->getName();
        }, $properties)));
    }

    /**
     * Restore the model after serialization.
     *
     * @return void
     */
    public function wakeup()
    {
        foreach ((new ReflectionClass($this))->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $value = $this->getPropertyValue($property);

            if (is_array($value) && array_keys($value) == ["class", "id", "relations", "connection"]){
                $value = new ModelIdentifier($value['class'], $value['id'], $value['relations'], $value['connection']);
            }

            $property->setValue($this, $this->getRestoredPropertyValue(
                $value
            ));
        }
    }

    /**
     * Get the property value for the given property.
     *
     * @param  \ReflectionProperty  $property
     * @return mixed
     */
    protected function getPropertyValue(ReflectionProperty $property)
    {
        $property->setAccessible(true);

        return $property->getValue($this);
    }
}
