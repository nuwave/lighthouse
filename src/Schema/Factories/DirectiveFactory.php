<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use Nuwave\Lighthouse\Schema\Directives\Args\ArgMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldManipulator;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldResolver;
use Nuwave\Lighthouse\Schema\Directives\Nodes\NodeManipulator;
use Nuwave\Lighthouse\Schema\Directives\Nodes\NodeMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Nodes\NodeResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Symfony\Component\Finder\Finder;

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
     * Register all of the commands in the given directory.
     *
     * https://github.com/laravel/framework/blob/5.5/src/Illuminate/Foundation/Console/Kernel.php#L190-L224
     *
     * @param array|string $paths
     * @param string|null  $namespace
     */
    public function load($paths, $namespace = null)
    {
        $paths = array_unique(is_array($paths) ? $paths : (array) $paths);
        $paths = array_map(function ($path) {
            return realpath($path);
        }, array_filter($paths, function ($path) {
            return is_dir($path);
        }));

        if (empty($paths)) {
            return;
        }

        $namespace = $namespace ?: app()->getNamespace();
        $path = starts_with($namespace, 'Nuwave\\Lighthouse')
            ? realpath(__DIR__.'/../../')
            : app_path();

        foreach ((new Finder())->in($paths)->files() as $directive) {
            $directive = $namespace.str_replace(
                ['/', '.php'],
                ['\\', ''],
                str_after($directive->getPathname(), $path.DIRECTORY_SEPARATOR)
            );

            if (! (new \ReflectionClass($directive))->isAbstract()) {
                $this->register($directive);
            }
        }
    }

    /**
     * Register a new directive handler.
     *
     * @param string $handler
     */
    public function register($handler)
    {
        $directive = app($handler);

        $this->directives->put($directive::name(), $directive);
    }

    /**
     * Get instance of handler for directive.
     *
     * @param string $name
     *
     * @return Directive
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
        return $this->forNode($node) instanceof NodeResolver;
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
        $resolvers = $this->handlers($node)->filter(function ($handler) {
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
     * @param $node
     *
     * @return \Illuminate\Support\Collection
     */
    public function nodeManipulators($node)
    {
        return $this->handlers($node)->filter(function (Directive $directive) {
            return $directive instanceof NodeManipulator;
        });
    }

    /**
     * @param $node
     *
     * @return \Illuminate\Support\Collection
     */
    public function fieldManipulators($node)
    {
        return $this->handlers($node)->filter(function (Directive $directive) {
            return $directive instanceof FieldManipulator;
        });
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
        return $this->handlers($node)->filter(function ($handler) {
            return $handler instanceof NodeMiddleware;
        });
    }

    /**
     * Get all handlers associated with the node's directives.
     *
     * @param Node $node
     *
     * @return \Illuminate\Support\Collection
     */
    protected function handlers(Node $node)
    {
        return collect(data_get($node, 'directives', []))->map(function (DirectiveNode $directive) {
            return $this->handler($directive->name->value);
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
        return $this->fieldResolver($field) instanceof FieldResolver;
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
        $resolvers = $this->handlers($field)->filter(function ($handler) {
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
        return $this->handlers($field)->filter(function ($handler) {
            return $handler instanceof FieldMiddleware;
        });
    }

    /**
     * Check if field has a resolver directive.
     *
     * @param FieldDefinitionNode $field
     *
     * @return bool
     */
    public function hasFieldMiddleware($field)
    {
        return $this->fieldMiddleware($field)->count() > 0;
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
        return $this->handlers($arg)->filter(function ($handler) {
            return $handler instanceof ArgMiddleware;
        });
    }
}
