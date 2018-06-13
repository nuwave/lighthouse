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
use Nuwave\Lighthouse\Schema\Directives\Fields\AbstractFieldDirective;
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
        $this->load(realpath(__DIR__.'/Directives/'), 'Nuwave\\Lighthouse\\');

        // Load custom directives
        $this->load(config('lighthouse.directives', []));
    }

    /**
     * Gather all directives from a given directory and register them.
     *
     * Works similar to https://github.com/laravel/framework/blob/5.6/src/Illuminate/Foundation/Console/Kernel.php#L191-L225
     *
     * @param array|string $paths
     * @param null         $namespace
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
            ? realpath(__DIR__.'/../../src/')
            : app_path();

        /** @var SplFileInfo $file */
        foreach ((new Finder())->in($paths)->files() as $file) {
            $className = $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    str_after($file->getPathname(), $path.DIRECTORY_SEPARATOR)
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
    protected function tryRegisterClassName($className)
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
        $this->directives->put($directive::name(), $directive);
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
    public function get($name)
    {
        $handler = $this->directives->get($name);

        if (! $handler) {
            throw new DirectiveException("No directive has been registered for [{$name}]");
        }

        return $handler;
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
    public function handler($name)
    {
        return $this->get($name);
    }

    /**
     * Get all directives associated with a node.
     *
     * @param Node $node
     *
     * @return \Illuminate\Support\Collection
     */
    protected function directives(Node $node)
    {
        return collect(data_get($node, 'directives', []))->map(function (DirectiveNode $directive) {
            return $this->get($directive->name->value);
        });
    }

    /**
     * @param Node $node
     *
     * @return \Illuminate\Support\Collection
     */
    public function typeManipulators(Node $node)
    {
        return $this->directives($node)->filter(function (Directive $directive) {
            return $directive instanceof TypeManipulator;
        });
    }

    /**
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return \Illuminate\Support\Collection
     */
    public function fieldManipulators(FieldDefinitionNode $fieldDefinition)
    {
        return $this->directives($fieldDefinition)->filter(function (Directive $directive) {
            return $directive instanceof FieldManipulator;
        })->map(function (AbstractFieldDirective $directive) use ($fieldDefinition) {
            return $directive->hydrate($fieldDefinition);
        });
    }

    /**
     * @param $inputValueDefinition
     *
     * @return \Illuminate\Support\Collection
     */
    public function argManipulators(InputValueDefinitionNode $inputValueDefinition)
    {
        return $this->directives($inputValueDefinition)->filter(function (Directive $directive) {
            return $directive instanceof ArgManipulator;
        });
    }

    /**
     * Get the type resolver directive for the given type definition.
     *
     * @param Node $typeDefinition
     *
     * @return TypeResolver
     */
    public function typeResolver(Node $typeDefinition)
    {
        $resolvers = $this->directives($typeDefinition)->filter(function (Directive $directive) {
            return $directive instanceof TypeResolver;
        });

        if ($resolvers->count() > 1) {
            $nodeName = data_get($typeDefinition, 'name.value');
            throw new DirectiveException("Node $nodeName can only have one TypeResolver directive. Check your schema definition");
        }

        return $resolvers->first();
    }

    /**
     * Check if the given node has a type resolver directive handler assigned to it.
     *
     * @param Node $typeDefinition
     *
     * @return bool
     */
    public function hasTypeResolver(Node $typeDefinition)
    {
        return $this->typeResolver($typeDefinition) instanceof TypeResolver;
    }

    /**
     * Get handler for field.
     *
     * @param FieldDefinitionNode $field
     *
     * @throws DirectiveException
     *
     * @return AbstractFieldDirective|null
     */
    public function fieldResolver($field)
    {
        $resolvers = $this->directives($field)->filter(function ($handler) {
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

        $resolver = $resolvers->first();

        return ($resolver instanceof AbstractFieldDirective) ? $resolver->hydrate($field) : null;
    }

    /**
     * Get all middleware directive for a type definitions.
     *
     * @param Node $typeDefinition
     *
     * @return \Illuminate\Support\Collection
     */
    public function typeMiddlewares(Node $typeDefinition)
    {
        return $this->directives($typeDefinition)->filter(function (Directive $directive) {
            return $directive instanceof TypeMiddleware;
        });
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
        return $this->directives($field)->filter(function ($handler) {
            return $handler instanceof FieldMiddleware;
        })->map(function (AbstractFieldDirective $fieldDirective) use ($field) {
            return $fieldDirective->hydrate($field);
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
        return $this->directives($arg)->filter(function ($handler) {
            return $handler instanceof ArgMiddleware;
        });
    }
}
