<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use ArrayAccess;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Node;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\NodeNodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Support\Contracts\Resolver;
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

            $reflection = (new \ReflectionClass($directive));
            if (!$reflection->isAbstract() && !$reflection->isTrait()) {
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
        /** @var Resolver $directive */
        $directive = app($handler);

        $this->directives->put($directive->name(), $directive);
    }

    /**
     * Get instance of handler for directive.
     *
     * @param string $name
     *
     * @return mixed
     * @throws DirectiveException
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
        $node->directives()->map(function (Node $node) {
            return $this->handler($node->name());
        })->reduce(function ($has, $handler){
            dd($has, $handler);
        });

        return $this->mapToName(data_get($node, 'directives', []), true)->reduce(function ($has, $handler) {
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
        $directiveNames = $this->mapToName(data_get($node, 'directives', []), true);
        $resolvers = $directiveNames->filter(function ($handler) {
            return $handler instanceof NodeResolver;
        });

        if ($resolvers->count() > 1) {
            throw new DirectiveException(sprintf(
                'Nodes can only have 1 assigned directive. %s has %s directives [%s]',
                data_get($node, 'name.value'),
                $directiveNames->count(),
                $directiveNames->implode(', ')
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
        return $this->mapToName(data_get($node, 'directives', []), true)->filter(function ($handler) {
            return $handler instanceof NodeNodeMiddleware;
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
        return $this->mapToName($field)->reduce(function ($has, $handler) {
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
        $directiveNames = $this->mapToName($field);
        $resolvers = $directiveNames->filter(function ($handler) {
            return $handler instanceof FieldResolver;
        });

        if ($resolvers->count() > 1) {
            throw new DirectiveException(sprintf(
                'Fields can only have 1 assigned resolver directive. %s has %s resolver directives [%s]',
                data_get($field, 'name.value'),
                $directiveNames->count(),
                $directiveNames->implode(', ')
            ));
        }

        return $resolvers->first();
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
        return $this->mapToName($field)->reduce(function ($has, $handler) {
            return $handler instanceof FieldMiddleware ? true : $has;
        }, false);
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
        return $this->mapToName($field)->filter(function ($handler) {
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
        return $this->mapToName($arg)->filter(function ($handler) {
            return $handler instanceof ArgMiddleware;
        });
    }

    /**
     * Maps a nodes directive to their name.
     *
     * @param $node
     * @param bool $isDirectives if node is directives then just convert them to name
     * @return \Illuminate\Support\Collection
     */
    private function mapToName($node, $isDirectives = false): Collection
    {
        return collect($isDirectives ? $node : $node->directives)->map(function (DirectiveNode $directive) {
                return $this->handler($directive->name->value);
            }
        );
    }
}
