<?php

namespace Nuwave\Lighthouse\Schema;

class Field
{
    /**
     * Field name.
     *
     * @var string
     */
    public $name;

    /**
     * Field namespace.
     *
     * @var string
     */
    public $namespace;

    /**
     * Middleware to be applied to field.
     *
     * @var array
     */
    public $middleware = [];

    /**
     * Create in instance of schema field.
     *
     * @param  string  $name
     * @param  string  $namespace
     * @return void
     */
    public function __construct($name, $namespace)
    {
        $this->name = $name;
        $this->namespace = $namespace;
    }

    /**
     * Resolve instance of field.
     *
     * @return mixed
     */
    public function resolve()
    {
        return app($this->namespace);
    }

    /**
     * Attach middleware to field.
     *
     * @param  array  $middleware
     * @return, void
     */
    public function addMiddleware(array $middleware)
    {
        $this->middleware = array_unique(array_merge($this->middleware, array_flatten($middleware)));
    }

    /**
     * Get field attributes.
     *
     * @return array
     */
    public function getAttributes()
    {
        return [
            'namespace' => $this->namespace,
            'middleware' => $this->middleware,
        ];
    }

    /**
     * Attach middleware(s) to field.
     *
     * @param  array|string  $middlewares
     * @return $this
     */
    public function middleware($middlewares)
    {
        $middlewares = is_array($middlewares) ? $middlewares : [$middlewares];

        foreach ($middlewares as $middleware) {
            $this->attachMiddleware($middleware);
        }

        return $this;
    }

    /**
     * Add middleware to collection.
     *
     * @param  string  $middleware
     * @return void
     */
    protected function attachMiddleware($middleware)
    {
        $this->middleware[] = $middleware;
    }
}
