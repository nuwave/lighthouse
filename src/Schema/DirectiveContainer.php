<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

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

    /**
     * Get handler for node.
     *
     * @param  Node   $node
     * @return mixed
     */
    public function forNode(Node $node)
    {
        $directives = data_get($node, 'directives');

        if (count($directives) > 1) {
            throw new DirectiveException(sprintf(
                "Nodes can only have 1 assigned directive. %s has %s directives [%s]",
                data_get($node, 'name.value'),
                count($directives),
                collect($directives)->map(function ($directive) {
                    return $directive->name->value;
                })->implode(", ")
            ));
        }

        return $this->handler($directives[0]->name->value);
    }
}
