<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use GraphQL\Language\AST\DirectiveNode;
use Symfony\Component\Finder\SplFileInfo;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\NodeResolver;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;

class DirectiveRegistry
{
    /**
     * Collection of registered directives.
     *
     * @var Collection
     */
    protected $directives;

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->directives = collect();

        // Load built-in directives from the default directory
        $this->load(
            realpath(__DIR__ . '/Directives/'),
            'Nuwave\\Lighthouse\\',
            \dirname(__DIR__)
        );
        // Load custom directives that are specified in the config
        $this->load(
            config('lighthouse.directives'),
            app()->getNamespace(),
            app_path()
        );
    }
    
    /**
     * Gather all directives from a given directory and register them.
     *
     * Works similar to
     * https://github.com/laravel/framework/blob/5.6/src/Illuminate/Foundation/Console/Kernel.php#L191-L225
     *
     * @param string[]|string $paths
     * @param string $rootNamespace
     * @param string $pathForRootNamespace
     *
     * @throws \ReflectionException
     *
     * @return DirectiveRegistry
     */
    public function load($paths, string $rootNamespace, string $pathForRootNamespace): DirectiveRegistry
    {
        $paths = collect($paths)
            ->unique()
            ->filter(function (string $path) {
                return is_dir($path);
            })
            ->map(function (string $path) {
                return realpath($path);
            })
            ->all();

        if (empty($paths)) {
            return $this;
        }

        $fileIterator = (new Finder)
            ->in($paths)
            ->files();

        /** @var SplFileInfo $file */
        foreach ($fileIterator as $file) {
            // Cut off the given root path to get the path that is equivalent to the namespace
            $namespaceRelevantPath = str_after(
                $file->getPathname(),
                // Call realpath to resolve relative paths, e.g. /foo/../bar -> /bar
                realpath($pathForRootNamespace) . DIRECTORY_SEPARATOR
            );
            
            $withoutExtension = str_before($namespaceRelevantPath, '.php');
            $fileNamespace = str_replace(DIRECTORY_SEPARATOR, '\\', $withoutExtension);

            $this->tryRegisterClassName($rootNamespace . $fileNamespace);
        }
        
        return $this;
    }
    
    /**
     * Register a directive class.
     *
     * @param string $className
     *
     * @throws \ReflectionException
     *
     * @return DirectiveRegistry
     */
    public function tryRegisterClassName(string $className): DirectiveRegistry
    {
        $reflection = new \ReflectionClass($className);

        if ($reflection->isInstantiable() && $reflection->isSubclassOf(Directive::class)) {
            $this->register(
                resolve($reflection->getName())
            );
        }
        
        return $this;
    }
    
    /**
     * Register a directive.
     *
     * @param Directive $directive
     *
     * @return DirectiveRegistry
     */
    public function register(Directive $directive): DirectiveRegistry
    {
        $this->directives->put($directive->name(), $directive);
        
        return $this;
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
        $directive = $this->directives->get($name);

        if (! $directive) {
            throw new DirectiveException("No directive has been registered for [{$name}]");
        }

        // Always return a new instance of the directive class to avoid side effects between them
        return resolve(\get_class($directive));
    }

    /**
     * Get all directives of a certain type that are associated with an AST node.
     *
     * @param Node $node
     * @param string $directiveClass
     *
     * @return Collection
     */
    protected function associatedDirectivesOfType(Node $node, string $directiveClass): Collection
    {
        return collect($node->directives)
            ->map(function (DirectiveNode $directive) {
                return $this->get($directive->name->value);
            })
            ->filter(function (Directive $directive) use ($directiveClass){
                return $directive instanceof $directiveClass;
            })
            ->map(function (Directive $directive) use ($node) {
                return $this->hydrate($directive, $node);
            });
    }

    /**
     * Get a single directive of a type that belongs to an AST node.
     *
     * Use this for directives types that can only occur once, such as field resolvers.
     * This throws if more than one such directive is found.
     *
     * @param Node $node
     * @param string $directiveClass
     *
     * @throws DirectiveException
     *
     * @return Directive|null
     */
    protected function singleDirectiveOfType(Node $node, string $directiveClass)
    {
        $directives = $this->associatedDirectivesOfType($node, $directiveClass);

        if ($directives->count() > 1) {
            $directiveNames = $directives->implode(', ');

            throw new DirectiveException(
                "Node [{$node->name->value}] can only have one directive of type [{$directiveClass}] but found [{$directiveNames}]"
            );
        }

        return $directives->first();
    }

    /**
     * @param Node $node
     *
     * @return Collection
     */
    public function nodeManipulators(Node $node): Collection
    {
        return $this->associatedDirectivesOfType($node, NodeManipulator::class);
    }

    /**
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @return Collection
     */
    public function fieldManipulators(FieldDefinitionNode $fieldDefinition): Collection
    {
        return $this->associatedDirectivesOfType($fieldDefinition, FieldManipulator::class);
    }

    /**
     * @param $inputValueDefinition
     *
     * @return Collection
     */
    public function argManipulators(InputValueDefinitionNode $inputValueDefinition): Collection
    {
        return $this->associatedDirectivesOfType($inputValueDefinition, ArgManipulator::class);
    }
    
    /**
     * Get the node resolver directive for the given type definition.
     *
     * @param TypeDefinitionNode $node
     *
     * @throws DirectiveException
     *
     * @return NodeResolver|null
     */
    public function nodeResolver(TypeDefinitionNode $node)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->singleDirectiveOfType($node, NodeResolver::class);
    }

    /**
     * Check if the given node has a type resolver directive handler assigned to it.
     *
     * @param TypeDefinitionNode $typeDefinition
     *
     * @throws DirectiveException
     *
     * @return bool
     */
    public function hasNodeResolver(TypeDefinitionNode $typeDefinition): bool
    {
        return $this->nodeResolver($typeDefinition) instanceof NodeResolver;
    }
    
    /**
     * Check if the given field has a field resolver directive handler assigned to it.
     *
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @throws DirectiveException
     *
     * @return bool
     */
    public function hasFieldResolver($fieldDefinition): bool
    {
        return $this->fieldResolver($fieldDefinition) instanceof FieldResolver;
    }

    /**
     * Check if field has one or more FieldMiddleware directives associated with it.
     *
     * @param FieldDefinitionNode $field
     *
     * @return bool
     */
    public function hasFieldMiddleware($field): bool
    {
        return $this->fieldMiddleware($field)->count() > 1;
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
        return $this->singleDirectiveOfType($field, FieldResolver::class);
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
        return $this->associatedDirectivesOfType($typeDefinition, NodeMiddleware::class);
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
        return $this->associatedDirectivesOfType($fieldDefinition, FieldMiddleware::class);
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
        return $this->associatedDirectivesOfType($arg, ArgMiddleware::class);
    }

    /**
     * Set the given definition on the directive.
     *
     * @param Directive                $directive
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
     * Get the node resolver directive for the given type definition.
     *
     * @param Node $node
     *
     * @throws DirectiveException
     *
     * @return NodeResolver
     * @deprecated in favour of nodeResolver()
     */
    public function forNode(Node $node)
    {
        return $this->nodeResolver($node);
    }
    
    /**
     * @param FieldDefinitionNode $fieldDefinition
     *
     * @throws DirectiveException
     *
     * @return bool
     * @deprecated in favour of hasFieldResolver()
     */
    public function hasResolver($fieldDefinition)
    {
        return $this->hasFieldResolver($fieldDefinition);
    }
}
