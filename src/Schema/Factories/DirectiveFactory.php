<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Schema\DirectiveNamespacer;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;

class DirectiveFactory
{
    /**
     * A map from short directive names to full class names.
     *
     * E.g.
     * [
     *   'create' => 'Nuwave\Lighthouse\Schema\Directives\CreateDirective',
     *   'custom' => 'App\GraphQL\Directives\CustomDirective',
     * ]
     *
     * @var string[]
     */
    protected $resolvedClassnames = [];

    /**
     * The paths used for locating directive classes.
     *
     * Should be tried in the order they are contained in this array,
     * going from the most significant to least significant.
     *
     * @var string[]
     */
    protected $directiveNamespaces;

    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveNamespacer
     */
    protected $directiveNamespacer;

    /**
     * DirectiveFactory constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\DirectiveNamespacer  $directiveNamespacer
     * @return void
     */
    public function __construct(DirectiveNamespacer $directiveNamespacer)
    {
        $this->directiveNamespacer = $directiveNamespacer;
    }

    /**
     * Create a directive by the given directive name.
     *
     * @param  string  $directiveName
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive
     */
    public function create(string $directiveName): Directive
    {
        return $this->resolve($directiveName)
            ?? $this->createOrFail($directiveName);
    }

    /**
     * Create a directive from resolved directive classes.
     *
     * @param  string  $directiveName
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive|null
     */
    protected function resolve(string $directiveName): ?Directive
    {
        if ($className = Arr::get($this->resolvedClassnames, $directiveName)) {
            return app($className);
        }

        return null;
    }

    /**
     * @param  string  $directiveName
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function createOrFail(string $directiveName): Directive
    {
        if (! $this->directiveNamespaces) {
            $this->directiveNamespaces = $this->directiveNamespacer->gather();
        }

        foreach ($this->directiveNamespaces as $baseNamespace) {
            $className = $baseNamespace.'\\'.static::className($directiveName);
            if (class_exists($className)) {
                $directive = app($className);

                if (! $directive instanceof Directive) {
                    throw new DirectiveException("Class $className is not a directive.");
                }

                $this->addResolved($directiveName, $className);

                return $directive;
            }
        }

        throw new DirectiveException("No directive found for `{$directiveName}`");
    }

    /**
     * Returns the expected class name for a directive name.
     *
     * @param  string  $directiveName
     * @return string
     */
    protected static function className(string $directiveName): string
    {
        return Str::studly($directiveName).'Directive';
    }

    /**
     * Returns the expected directive name for a class name.
     *
     * @param  string  $className
     * @return string
     */
    public static function directiveName(string $className): string
    {
        $baseName = basename(str_replace('\\', '/', $className));

        return lcfirst(
            Str::before($baseName, 'Directive')
        );
    }

    /**
     * @deprecated use the RegisterDirectiveNamespaces event instead, this method will be removed as of v5
     * @see \Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces
     *
     * @param  string  $directiveName
     * @param  string  $className
     * @return $this
     */
    public function addResolved(string $directiveName, string $className): self
    {
        // Bail to respect the priority of namespaces, the first
        // resolved directive is kept
        if (in_array($directiveName, $this->resolvedClassnames, true)) {
            return $this;
        }

        $this->resolvedClassnames[$directiveName] = $className;

        return $this;
    }

    /**
     * @param  string  $directiveName
     * @param  string  $className
     * @return $this
     */
    public function setResolved(string $directiveName, string $className): self
    {
        $this->resolvedClassnames[$directiveName] = $className;

        return $this;
    }

    /**
     * @deprecated will be removed as of v5
     * @return $this
     */
    public function clearResolved(): self
    {
        $this->resolvedClassnames = [];

        return $this;
    }

    /**
     * Get all directives of a certain type that are associated with an AST node.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  string  $directiveClass
     * @return \Illuminate\Support\Collection of type <$directiveClass>
     */
    public function createAssociatedDirectivesOfType(Node $node, string $directiveClass): Collection
    {
        return $this
            ->createAssociatedDirectives($node)
            ->filter(function (Directive $directive) use ($directiveClass): bool {
                return $directive instanceof $directiveClass;
            });
    }

    /**
     * Get all directives that are associated with an AST node.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @return \Illuminate\Support\Collection of type <$directiveClass>
     */
    public function createAssociatedDirectives(Node $node): Collection
    {
        return (new Collection($node->directives))
            ->map(function (DirectiveNode $directiveNode) use ($node): Directive {
                $directive = $this->create($directiveNode->name->value);

                if ($directive instanceof BaseDirective) {
                    $directive->hydrate($directiveNode, $node);
                }

                return $directive;
            });
    }

    /**
     * Get a single directive of a type that belongs to an AST node.
     *
     * Use this for directives types that can only occur once, such as field resolvers.
     * This throws if more than one such directive is found.
     *
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  string  $directiveClass
     * @return \Nuwave\Lighthouse\Support\Contracts\Directive|null
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function createSingleDirectiveOfType(Node $node, string $directiveClass): ?Directive
    {
        $directives = $this->createAssociatedDirectivesOfType($node, $directiveClass);

        if ($directives->count() > 1) {
            $directiveNames = $directives->implode(', ');

            throw new DirectiveException(
                "Node [{$node->name->value}] can only have one directive of type [{$directiveClass}] but found [{$directiveNames}]"
            );
        }

        return $directives->first();
    }
}
