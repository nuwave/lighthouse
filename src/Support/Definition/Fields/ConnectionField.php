<?php

namespace Nuwave\Lighthouse\Support\Definition\Fields;

use Closure;
use Illuminate\Support\Fluent;
use Nuwave\Lighthouse\Schema\Generators\ConnectionResolveGenerator as Resolver;

class ConnectionField extends Fluent
{
    /**
     * Connection auto resolver.
     *
     * @var Resolver
     */
    protected $resolver;

    /**
     * Set resolve function on field.
     *
     * @param  Closure $resolve
     * @return self
     */
    public function resolve(Closure $resolve)
    {
        $this->__set('resolve', $resolve);

        return $this;
    }

    /**
     * Encode/Decode connection cursor.
     *
     * @param  Closure $encode
     * @param  Closure|null $decode
     * @return self
     */
    public function cursor(Closure $encode, Closure $decode = null)
    {
        $name = $this->get('type')->name;

        app('graphql')->schema()->cursor($name, $encode, $decode);

        return $this;
    }

    /**
     * Add agruments to field.
     *
     * @param  array  $args
     * @param  bool $append
     * @return self
     */
    public function args(array $args, $append = true)
    {
        $arguments = $append ? array_merge($this->get('args'), $args) : $args;

        $this->__set('args', $arguments);

        return $this;
    }

    /**
     * Auto resolve the connection.
     *
     * @param  string $name
     * @return Closure
     */
    protected function autoResolve($name)
    {
        if (! $name) {
            $connection = $this->get('type')->name;

            throw new \Exception("A name must be provided for [$connection] when auto resolving.");
        }

        return function ($root, array $args, $context, $info) use ($name) {
            return $this->getResolver()->resolve($root, $args, $context, $info, $name);
        };
    }

    /**
     * Set local instance of resolver.
     *
     * @param Resolver $resolver
     */
    public function setResolver(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Get instance of resolver.
     *
     * @return Resolver
     */
    protected function getResolver()
    {
        return $this->resolver ?: new Resolver;
    }

    /**
     * Convert to GraphQL field.
     *
     * @return array
     */
    public function field($name = null)
    {
        if (! $this->get('resolve')) {
            $this->__set('resolve', $this->autoResolve($name));
        }

        return $this->toArray();
    }
}
