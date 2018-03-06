<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class DirectiveFactory
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
     * @param string $handler
     */
    public function register($handler)
    {
        $directive = app($handler);

        $this->directives->put($directive->name(), $directive);
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
            throw new DirectiveException("No directive has been registered for [{$name}]");
        }

        return $handler;
    }

    /**
     * Check if field has a resolver directive.
     *
     * @param Node $node
     *
     * @return bool
     */
    public function hasNodeResolver(Node $node)
    {
        return collect(data_get($node, 'directives', []))->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->reduce(function ($has, $handler) {
            return $handler instanceof NodeResolver ? true : $has;
        }, false);
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
        $resolvers = collect(data_get($node, 'directives', []))->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->filter(function ($handler) {
            return $handler instanceof NodeResolver;
        });

        if ($resolvers->count() > 1) {
            throw new DirectiveException(sprintf(
                'Nodes can only have 1 assigned directive. %s has %s directives [%s]',
                data_get($node, 'name.value'),
                count($directives),
                collect($directives)->map(function ($directive) {
                    return $directive->name->value;
                })->implode(', ')
            ));
        }

        return $resolvers->first();
    }

    /**
     * Get middleware for field.
     *
     * @param Node $node
     *
     * @return \Illuminate\Support\Collection
     */
    public function nodeMiddleware(Node $node)
    {
        return collect(data_get($node, 'directives', []))->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->filter(function ($handler) {
            return $handler instanceof NodeMiddleware;
        });
    }

    /**
     * Check if field has a resolver directive.
     *
     * @param FieldDefinitionNode $field
     *
     * @return bool
     */
    public function hasResolver($field)
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
    public function fieldResolver($field)
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
    public function fieldMiddleware($field)
    {
        return collect($field->directives)->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->filter(function ($handler) {
            return $handler instanceof FieldMiddleware;
        });
    }

    /**
     * Get middleware for field arguments.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return \Illuminate\Support\Collection
     */
    public function argMiddleware(InputValueDefinitionNode $arg)
    {
        return collect($arg->directives)->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
        })->filter(function ($handler) {
            return $handler instanceof ArgMiddleware;
        });
    }
}
