<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Directives\Args\ArgManipulator;
use Nuwave\Lighthouse\Schema\Directives\Args\ArgMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Directive;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldManipulator;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Fields\FieldResolver;
use Nuwave\Lighthouse\Schema\Directives\Types\TypeManipulator;
use Nuwave\Lighthouse\Schema\Directives\Types\TypeMiddleware;
use Nuwave\Lighthouse\Schema\Directives\Types\TypeResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DirectiveRegistry
{
    /**
     * Collection of registered directives.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $directives;

    /**
     * Create new instance of the directive container.
     */
    public function __construct()
    {
        $this->directives = new Collection();

        // Load built-in directives from the default directory
        $this->load(realpath(__DIR__ . '/Directives/'), 'Nuwave\\Lighthouse\\');

        // Load custom directives
        $this->load(config('lighthouse.directives', []));
    }

    /**
     * Gather all directives from a given directory and register them.
     *
     * Works similar to https://github.com/laravel/framework/blob/5.6/src/Illuminate/Foundation/Console/Kernel.php#L191-L225
     *
     * @param array|string $paths
     * @param null $namespace
     */
    public function load($paths, $namespace = null)
    {
        $paths = collect($paths)
            ->unique()
            ->filter(function ($path) {
                return is_dir($path);
            })->map(function ($path) {
                return realpath($path);
            })->all();

        if (empty($paths)) {
            return;
        }

        $namespace = $namespace ?: app()->getNamespace();
        $path = starts_with($namespace, 'Nuwave\\Lighthouse')
            ? realpath(__DIR__ . '/../../src/')
            : app_path();

        /** @var SplFileInfo $file */
        foreach ((new Finder())->in($paths)->files() as $file) {
            $className = $namespace . str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    str_after($file->getPathname(), $path . DIRECTORY_SEPARATOR)
            );

            $this->register($className);
        }
    }

    /**
     * Register a directive class.
     *
     * @param string $className
     * @throws \ReflectionException
     */
    protected function register($className)
    {
        $reflection = new \ReflectionClass($className);

        if ($reflection->isInstantiable() && $reflection->isSubclassOf(Directive::class)) {
            $directive = $reflection->newInstance();
            $this->directives->put($directive::name(), $directive);
        }
    }

    /**
     * Get instance of handler for directive.
     *
     * @param string $name
     *
     * @return Directive
     * @throws DirectiveException
     */
    public function handler($name)
    {
        $handler = $this->directives->get($name);

        if (!$handler) {
            throw new DirectiveException("No directive has been registered for [{$name}]");
        }

        return $handler;
    }

    /**
     * Check if the given node has a type resolver directive handler assigned to it.
     *
     * @param Node $node
     *
     * @return bool
     */
    public function hasTypeResolver(Node $node)
    {
        return $this->typeResolverForNode($node) instanceof TypeResolver;
    }

    /**
     * Get the type resolver directive handler for the given node.
     *
     * @param Node $node
     *
     * @return TypeResolver
     */
    public function typeResolverForNode(Node $node)
    {
        $resolvers = $this->handlers($node)->filter(function ($handler) {
            return $handler instanceof TypeResolver;
        });

        if ($resolvers->count() > 1) {
            $nodeName = data_get($node, 'name.value');
            throw new DirectiveException("Node $nodeName can only have one TypeResolver directive. Check your schema definition");
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
            return $directive instanceof TypeManipulator;
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
     * @param $node
     *
     * @return \Illuminate\Support\Collection
     */
    public function argManipulators($node)
    {
        return $this->handlers($node)->filter(function (Directive $directive) {
            return $directive instanceof ArgManipulator;
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
            return $handler instanceof TypeMiddleware;
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
