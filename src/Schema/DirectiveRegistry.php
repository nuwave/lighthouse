<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class DirectiveRegistry
{
    /**
     * Collection of registered directives.
     *
     * @var Collection
     */
    protected $directives;

    /**
     * Create new instance of the directive container.
     */
    public function __construct()
    {
        $this->directives = collect();

        // Load built-in directives from the default directory
        $this->load(realpath(__DIR__ . '/Directives/'), 'Nuwave\\Lighthouse\\');

        // Load custom directives
        $this->load(config('lighthouse.directives', []));
    }

    /**
     * Gather all directives from a given directory and register them.
     *
     * Works similar to
     * https://github.com/laravel/framework/blob/5.6/src/Illuminate/Foundation/Console/Kernel.php#L191-L225
     *
     * @param array|string $paths
     * @param string|null $namespace
     *
     * @throws \ReflectionException
     */
    protected function load($paths, $namespace = null)
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

            $this->tryRegisterClassName($className);
        }
    }

    /**
     * Register a directive class.
     *
     * @param string $className
     *
     * @throws \ReflectionException
     */
    protected function tryRegisterClassName(string $className)
    {
        $reflection = new \ReflectionClass($className);

        if ($reflection->isInstantiable() && $reflection->isSubclassOf(Directive::class)) {
            $directive = $reflection->newInstance();
            $this->register($directive);
        }
    }

    /**
     * Register a directive.
     *
     * @param Directive $directive
     */
    public function register(Directive $directive)
    {
        $this->directives->put($directive->name(), $directive);
    }

    /**
     * Get directive instance by name.
     *
     * @param string $name
     *
     * @throws DirectiveException
     *
     * @return Directive
     *
     * @deprecated Will be removed in next major release
     */
    public function handler(string $name): Directive
    {
        return $this->get($name);
    }

    /**
     * Get directive instance by name.
     *
     * @param string $name
     *
     * @throws DirectiveException
     *
     * @return Directive
     */
    public function get(string $name): Directive
    {
        $handler = $this->directives->get($name);

        if (!$handler) {
            throw new DirectiveException("No directive has been registered for [{$name}]");
        }

        return $handler;
    }

    /**
     * @param Node $node
     *
     * @return Collection
     */
    public function nodeManipulators(Node $node): Collection
    {
        return $this->directives($node)->filter(function (Directive $directive) {
            return $directive instanceof NodeManipulator;
        });
    }

    /**
     * Get all directives associated with a node.
     *
     * @param Node $node
     *
     * @return Collection
     */
    protected function directives(Node $node): Collection
    {
        return collect(data_get($node, 'directives', []))
            ->map(function (DirectiveNode $directive) {
                return $this->get($directive->name->value);
            })->map(function (Directive $directive) use ($node) {
                return $this->hydrate($directive, $node);
            });
    }

    /**
     * @param Directive $directive
     * @param TypeSystemDefinitionNode $definitionNode
     *
     * @return Directive
     */
    protected function hydrate(Directive $directive, $definitionNode): Directive
    {
        return $directive instanceof BaseDirective
            ? $directive->hydrate($definitionNode)
            : $directive;
    }

    /**
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return Collection
     */
    public function fieldManipulators(FieldDefinitionNode $fieldDefinition): Collection
    {
        return $this->directives($fieldDefinition)->filter(function (Directive $directive) {
            return $directive instanceof FieldManipulator;
        });
    }

    /**
     * @param $inputValueDefinition
     *
     * @return Collection
     */
    public function argManipulators(InputValueDefinitionNode $inputValueDefinition): Collection
    {
        return $this->directives($inputValueDefinition)->filter(function (Directive $directive) {
            return $directive instanceof ArgManipulator;
        });
    }

    /**
     * Get the node resolver directive for the given type definition.
     *
     * @param Node $node
     *
     * @return NodeResolver|null
     * @throws DirectiveException
     * @deprecated in favour of nodeResolver
     */
    public function forNode(Node $node)
    {
        return $this->nodeResolver($node);
    }

    /**
     * Get the node resolver directive for the given type definition.
     *
     * @param Node $node
     *
     * @return NodeResolver|null
     * @throws DirectiveException
     */
    public function nodeResolver(Node $node)
    {
        $resolvers = $this->directives($node)->filter(function (Directive $directive) {
            return $directive instanceof NodeResolver;
        });

        if ($resolvers->count() > 1) {
            $nodeName = data_get($node, 'name.value');
            throw new DirectiveException("Node $nodeName can only have one NodeResolver directive. Check your schema definition");
        }


        return $resolvers->first();
    }

    /**
     * Check if the given node has a type resolver directive handler assigned to it.
     *
     * @param Node $typeDefinition
     *
     * @return bool
     * @throws DirectiveException
     */
    public function hasNodeResolver(Node $typeDefinition): bool
    {
        return $this->nodeResolver($typeDefinition) instanceof NodeResolver;
    }

    /**
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return bool
     * @throws DirectiveException
     * @deprecated in favour of hasFieldResolver
     */
    public function hasResolver($fieldDefinition): bool
    {
        return $this->hasFieldResolver($fieldDefinition);
    }

    /**
     * Check if the given field has a field resolver directive handler assigned to it.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return bool
     * @throws DirectiveException
     */
    public function hasFieldResolver($fieldDefinition): bool
    {
        return $this->fieldResolver($fieldDefinition) instanceof FieldResolver;
    }

    /**
     * Get handler for field.
     *
     * @param FieldDefinitionNode $field
     *
     * @throws DirectiveException
     *
     * @return FieldResolver|null
     */
    public function fieldResolver($field)
    {
        $resolvers = $this->directives($field)->filter(function ($directive) {
            return $directive instanceof FieldResolver;
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
     * Check if field has a resolver directive.
     *
     * @param FieldDefinitionNode $field
     *
     * @return bool
     */
    public function hasFieldMiddleware($field): bool
    {
        return collect($field->directives)->map(function (DirectiveNode $directive) {
            return $this->get($directive->name->value);
        })->reduce(function ($has, $handler) {
            return $handler instanceof FieldMiddleware ? true : $has;
        }, false);
    }

    /**
     * Get all middleware directive for a type definitions.
     *
     * @param Node $typeDefinition
     *
     * @return Collection
     */
    public function nodeMiddleware(Node $typeDefinition): Collection
    {
        return $this->directives($typeDefinition)->filter(function (Directive $directive) {
            return $directive instanceof NodeMiddleware;
        });
    }

    /**
     * Get middleware for field.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return Collection
     */
    public function fieldMiddleware($fieldDefinition): Collection
    {
        return $this->directives($fieldDefinition)->filter(function ($handler) {
            return $handler instanceof FieldMiddleware;
        });
    }

    /**
     * Get middleware for field arguments.
     *
     * @param InputValueDefinitionNode $arg
     *
     * @return Collection
     */
    public function argMiddleware(InputValueDefinitionNode $arg): Collection
    {
        return $this->directives($arg)->filter(function (Directive $directive) {
            return $directive instanceof ArgMiddleware;
        });
    }
}
