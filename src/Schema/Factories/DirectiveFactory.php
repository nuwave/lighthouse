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
use Nuwave\Lighthouse\Support\Utils;

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

    public function __construct(DirectiveNamespacer $directiveNamespacer)
    {
        $this->directiveNamespacer = $directiveNamespacer;
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
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function resolve(string $directiveName): string
    {
        if ($directiveClass = Arr::get($this->resolvedClassnames, $directiveName)) {
            return $directiveClass;
        }

        if (! $this->directiveNamespaces) {
            $this->directiveNamespaces = $this->directiveNamespacer->gather();
        }

        foreach ($this->directiveNamespaces as $baseNamespace) {
            $directiveClass = $baseNamespace.'\\'.static::className($directiveName);

            if (class_exists($directiveClass)) {
                if (! is_a($directiveClass, Directive::class, true)) {
                    throw new DirectiveException("Class $directiveClass must implement the interface ".Directive::class);
                }

                $this->addResolved($directiveName, $directiveClass);

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
        return Str::studly($directiveName).'Directive';
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
     * @deprecated use the RegisterDirectiveNamespaces event instead, this method will be removed as of v5
     * @see \Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces
     *
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
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive> of type <$directiveClass>
     */
    public function createAssociatedDirectivesOfType(Node $node, string $directiveClass): Collection
    {
        return $this
            ->createAssociatedDirectives($node)
            ->filter(Utils::instanceofMatcher($directiveClass));
    }

    /**
     * Get all directives that are associated with an AST node.
     *
     * @return \Illuminate\Support\Collection<\Nuwave\Lighthouse\Support\Contracts\Directive>
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
     * TODO rename to exclusiveDirective
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function createSingleDirectiveOfType(Node $node, string $directiveClass): ?Directive
    {
        $directives = $this->createAssociatedDirectivesOfType($node, $directiveClass);

        if ($directives->count() > 1) {
            $directiveNames = $directives
                ->map(function (Directive $directive): string {
                    return '@'.$directive->name();
                })
                ->implode(', ');

            throw new DirectiveException(
                "Node {$node->name->value} can only have one directive of type {$directiveClass} but found [{$directiveNames}]."
            );
        }

        return $directives->first();
    }
}
