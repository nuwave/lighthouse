<?php

namespace Nuwave\Lighthouse\Schema;

class DirectiveContainer
{
    /**
     * Collection of registered directives.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $directives;

    /**
     * Create new instance of directive container.
     */
    public function __construct()
    {
        $this->directives = collect();
    }

    /**
     * Regsiter a new directive handler.
     *
     * @param  string $name
     * @param  [type] $handler
     * @return void
     */
    public function register($name, $handler)
    {
        $this->directives->put($name, $handler);
    }

    /**
     * Get instance of handler for directive.
     *
     * @param  string $name
     * @return mixed
     */
    public function handler($name)
    {
        $handler = $this->directives->get($name);

        if (!$handler) {
            throw new \Exception("No directive has been registered for [{$name}]");
        }

        return $handler;
    }
}
