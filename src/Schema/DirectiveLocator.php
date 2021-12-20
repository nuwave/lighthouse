<?php

namespace Nuwave\Lighthouse\Schema;

use Exception;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\Node;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Utils;

class DirectiveLocator
{
    /**
     * The paths used for locating directive classes.
     *
     * Should be tried in the order they are contained in this array,
     * going from the most significant to least significant.
     *
     * Lazily initialized.
     *
     * @var array<int, string>
     */
    protected $directiveNamespaces;

    /**
     * A map from short directive names to full class names.
     *
     * E.g.
     * [
     *   'create' => 'Nuwave\Lighthouse\Schema\Directives\CreateDirective',
     *   'custom' => 'App\GraphQL\Directives\CustomDirective',
     * ]
     *
     * @var array<string, class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>>
     */
    protected $resolvedClassnames = [];

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    public function __construct(EventsDispatcher $eventsDispatcher)
    {
        $this->eventsDispatcher = $eventsDispatcher;
    }

    /**
     * A list of namespaces with directives in descending priority.
     *
     * @return array<int, string>
     */
    public function namespaces(): array
    {
        if (null === $this->directiveNamespaces) {
            $this->directiveNamespaces
                // When looking for a directive by name, the namespaces are tried in order
                = (new Collection([
                    // User defined directives (top priority)
                    config('lighthouse.namespaces.directives'),

                    // Plugin developers defined directives
                    $this->eventsDispatcher->dispatch(new RegisterDirectiveNamespaces()),

                    // Lighthouse defined directives
                    'Nuwave\\Lighthouse\\Schema\\Directives',
                ]))
                ->flatten()
                ->filter()
                ->all();
        }

        return $this->directiveNamespaces;
    }

    /**
     * Scan the namespaces for directive classes.
     *
     * @return array<string, class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>>
     */
    public function classes(): array
    {
        $directives = [];

        foreach ($this->namespaces() as $directiveNamespace) {
            /** @var array<class-string> $classesInNamespace */
            $classesInNamespace = ClassFinder::getClassesInNamespace($directiveNamespace);

            foreach ($classesInNamespace as $class) {
                $reflection = new \ReflectionClass($class);
                if (! $reflection->isInstantiable()) {
                    continue;
                }

                if (! is_a($class, Directive::class, true)) {
                    continue;
                }
                $name = self::directiveName($class);

                // The directive was already found, so we do not add it twice
                if (isset($directives[$name])) {
                    continue;
                }

                $directives[$name] = $class;
            }
        }

        return $directives;
    }

    /**
     * Return the parsed definitions for all directive classes.
     *
     * @return list<\GraphQL\Language\AST\DirectiveDefinitionNode>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->classes() as $directiveClass) {
            $definitions[] = ASTHelper::extractDirectiveDefinition($directiveClass::definition());
        }

        return $definitions;
    }

    /**
     * Create a directive by the given directive name.
     */
    public function create(string $directiveName): Directive
    {
        $directiveClass = $this->resolve($directiveName);

        return app($directiveClass);
    }

    /**
     * Resolve the class for a given directive name.
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     *
     * @return class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public function resolve(string $directiveName): string
    {
        // Bail to respect the priority of namespaces, the first resolved directive is kept
        if (array_key_exists($directiveName, $this->resolvedClassnames)) {
            return $this->resolvedClassnames[$directiveName];
        }

        foreach ($this->namespaces() as $baseNamespace) {
            $directiveClass = $baseNamespace . '\\' . static::className($directiveName);

            if (class_exists($directiveClass)) {
                if (! is_a($directiveClass, Directive::class, true)) {
                    throw new DirectiveException("Class $directiveClass must implement the interface " . Directive::class);
                }
                $this->resolvedClassnames[$directiveName] = $directiveClass;

                return $directiveClass;
            }
        }

        throw new DirectiveException("No directive found for `{$directiveName}`");
    }

    /**
     * Returns the expected class name for a directive name.
     */
    protected static function className(string $directiveName): string
    {
        return Str::studly($directiveName) . 'Directive';
    }

    /**
     * Returns the expected directive name for a class name.
     */
    public static function directiveName(string $className): string
    {
        $baseName = basename(str_replace('\\', '/', $className));

        return lcfirst(
            Str::before($baseName, 'Directive')
        );
    }

    /**
     * @param  class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directiveClass
     */
    public function setResolved(string $directiveName, string $directiveClass): self
    {
        $this->resolvedClassnames[$directiveName] = $directiveClass;

        return $this;
    }

    /**
     * Get all directives that are associated with an AST node.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public function associated(Node $node): Collection
    {
        if (! property_exists($node, 'directives')) {
            throw new Exception('Expected Node class with property `directives`, got: ' . get_class($node));
        }

        return (new Collection($node->directives))
            ->map(function (DirectiveNode $directiveNode) use ($node): Directive {
                $directive = $this->create($directiveNode->name->value);

                if ($directive instanceof BaseDirective) {
                    // @phpstan-ignore-next-line If there were directives on the given Node, it must be of an allowed type
                    $directive->hydrate($directiveNode, $node);
                }

                return $directive;
            });
    }

    /**
     * Get all directives of a certain type that are associated with an AST node.
     *
     * @template TDirective of \Nuwave\Lighthouse\Support\Contracts\Directive
     *
     * @param  class-string<TDirective>  $directiveClass
     *
     * @return \Illuminate\Support\Collection<TDirective>
     */
    public function associatedOfType(Node $node, string $directiveClass): Collection
    {
        /**
         * Ensured by instanceofMatcher.
         *
         * @var \Illuminate\Support\Collection<TDirective> $associatedOfType
         */
        $associatedOfType = $this
            ->associated($node)
            ->filter(Utils::instanceofMatcher($directiveClass));

        return $associatedOfType;
    }

    /**
     * Get a single directive of a type that belongs to an AST node.
     *
     * Use this for directives types that can only occur once, such as field resolvers.
     * This throws if more than one such directive is found.
     *
     * @template TDirective of \Nuwave\Lighthouse\Support\Contracts\Directive
     *
     * @param  class-string<TDirective>  $directiveClass
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     *
     * @return TDirective|null
     */
    public function exclusiveOfType(Node $node, string $directiveClass): ?Directive
    {
        $directives = $this->associatedOfType($node, $directiveClass);

        if ($directives->count() > 1) {
            $directiveNames = $directives
                ->map(function (Directive $directive): string {
                    $definition = ASTHelper::extractDirectiveDefinition(
                        $directive::definition()
                    );

                    return '@' . $definition->name->value;
                })
                ->implode(', ');

            if (! property_exists($node, 'name')) {
                throw new Exception('Expected Node class with property `name`, got: ' . get_class($node));
            }

            throw new DirectiveException(
                "Node {$node->name->value} can only have one directive of type {$directiveClass} but found [{$directiveNames}]."
            );
        }

        return $directives->first();
    }
}
