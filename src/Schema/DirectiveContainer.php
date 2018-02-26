<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
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
     * @param string $name
     * @param [type] $handler
     */
    public function register($name, $handler)
    {
        $this->directives->put($name, $handler);
    }

    /**
     * Get instance of handler for directive.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function handler($name)
    {
        $handler = $this->directives->get($name);

        if (! $handler) {
            throw new \Exception("No directive has been registered for [{$name}]");
        }

        return $handler;
    }

    /**
     * Get handler for node.
     *
     * @param Node $node
     *
     * @return mixed
     */
    public function forNode(Node $node)
    {
        $directives = data_get($node, 'directives');

        if (count($directives) > 1) {
            throw new DirectiveException(sprintf(
                'Nodes can only have 1 assigned directive. %s has %s directives [%s]',
                data_get($node, 'name.value'),
                count($directives),
                collect($directives)->map(function ($directive) {
                    return $directive->name->value;
                })->implode(', ')
            ));
        }

        // TODO: This should return the handler and not the resolved type.
        return $this->handler($directives[0]->name->value);
    }

    /**
     * Check if field has a resolver directive.
     *
     * @param FieldDefinitionNode $field
     *
     * @return bool
     */
    public function hasResolver(FieldDefinitionNode $field)
    {
        return collect($field->directives)->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->reduce(function ($has, $handler) {
            return $handler instanceof FieldResolver ? true : $has;
        }, false);
    }

    /**
     * Get handler for field.
     *
     * @param FieldDefinitionNode $field
     *
     * @return mixed
     */
    public function fieldResolver(FieldDefinitionNode $field)
    {
        $resolvers = collect($field->directives)->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->filter(function ($handler) {
            return $handler instanceof FieldResolver;
        });

        if ($resolvers->count() > 1) {
            throw new DirectiveException(sprintf(
                'Fields can only have 1 assigned resolver directive. %s has %s resolver directives [%s]',
                data_get($field, 'name.value'),
                $resolvers->count(),
                collect($field->directives)->map(function (DirectiveNode $directive) {
                    return $directive->name->value;
                })->implode(', ')
            ));
        }

        return $resolvers->first();
    }

    /**
     * Get middleware for field.
     *
     * @param FieldDefinitionNode $field
     *
     * @return \Illuminate\Support\Collection
     */
    public function fieldMiddleware(FieldDefinitionNode $field)
    {
        return collect($field->directives)->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->filter(function ($handler) {
            return $handler instanceof FieldMiddleware;
        });
    }
}
