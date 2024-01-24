<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\Node;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Container\Container;
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
    protected array $directiveNamespaces;

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
    protected array $resolvedClassnames = [];

    public function __construct(
        protected EventsDispatcher $eventsDispatcher,
    ) {}

    /**
     * A list of namespaces with directives in descending priority.
     *
     * @return array<int, string>
     */
    public function namespaces(): array
    {
        return $this->directiveNamespaces
            // When looking for a directive by name, the namespaces are tried in order
            ??= (new Collection([
                // User defined directives come first
                config('lighthouse.namespaces.directives'),

                // Built-in and plugin defined directives come next
                $this->eventsDispatcher->dispatch(new RegisterDirectiveNamespaces()),
            ]))
            ->flatten()
            ->filter()
            // Ensure built-in directives come last
            ->sortBy(static fn (string $namespace): int => (int) str_starts_with($namespace, 'Nuwave\\Lighthouse'))
            ->all();
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

                // Only add the first directive that was found
                $directives[self::directiveName($class)] ??= $class;
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

    /** Create a directive by the given directive name. */
    public function create(string $directiveName): Directive
    {
        $directiveClass = $this->resolve($directiveName);

        return Container::getInstance()->make($directiveClass);
    }

    /**
     * Resolve the class for a given directive name.
     *
     * @return class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public function resolve(string $directiveName): string
    {
        if (array_key_exists($directiveName, $this->resolvedClassnames)) {
            return $this->resolvedClassnames[$directiveName];
        }

        foreach ($this->namespaces() as $directiveNamespace) {
            $directiveClass = $directiveNamespace . '\\' . static::className($directiveName);

            if (class_exists($directiveClass)) {
                $directiveInterface = Directive::class;
                if (! is_a($directiveClass, $directiveInterface, true)) {
                    throw new DirectiveException("Class {$directiveClass} must implement the interface {$directiveInterface}.");
                }

                $this->resolvedClassnames[$directiveName] = $directiveClass;

                // Bail to respect the priority of namespaces, the first resolved directive is kept
                return $directiveClass;
            }
        }

        throw new DirectiveException("No directive found for `{$directiveName}`");
    }

    /** Returns the expected class name for a directive name. */
    protected static function className(string $directiveName): string
    {
        return Str::studly($directiveName) . 'Directive';
    }

    /** Returns the expected directive name for a class name. */
    public static function directiveName(string $className): string
    {
        $baseName = basename(str_replace('\\', '/', $className));

        return lcfirst(
            Str::beforeLast($baseName, 'Directive'),
        );
    }

    /** @param  class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directiveClass */
    public function setResolved(string $directiveName, string $directiveClass): self
    {
        $this->resolvedClassnames[$directiveName] = $directiveClass;

        return $this;
    }

    /**
     * Get all directives that are associated with an AST node.
     *
     * @return \Illuminate\Support\Collection<int, \Nuwave\Lighthouse\Support\Contracts\Directive>
     */
    public function associated(Node $node): Collection
    {
        if (! property_exists($node, 'directives')) {
            throw new \Exception('Expected Node class with property `directives`, got: ' . $node::class);
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
     * @return \Illuminate\Support\Collection<int, TDirective>
     */
    public function associatedOfType(Node $node, string $directiveClass): Collection
    {
        /**
         * Ensured by instanceofMatcher.
         *
         * @var \Illuminate\Support\Collection<int, TDirective> $associatedOfType
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
     * @return TDirective|null
     */
    public function exclusiveOfType(Node $node, string $directiveClass): ?Directive
    {
        $directives = $this->associatedOfType($node, $directiveClass);

        if ($directives->count() > 1) {
            if (! property_exists($node, 'name')) {
                $unnamedNode = $node::class;
                throw new \Exception("Expected Node class with property `name`, got: {$unnamedNode}.");
            }

            $directiveNames = $directives
                ->map(static function (Directive $directive): string {
                    $definition = ASTHelper::extractDirectiveDefinition(
                        $directive::definition(),
                    );

                    return "@{$definition->name->value}";
                })
                ->implode(', ');

            throw new DirectiveException("Node {$node->name->value} can only have one directive of type {$directiveClass} but found [{$directiveNames}].");
        }

        return $directives->first();
    }
}
