<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives\Traits;

use Illuminate\Contracts\Database\Query\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait HasBuilderArgument
{
    /** @param  array<string, mixed>  $args */
    private function makeBuilder(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder|ScoutBuilder
    {
        if (! $this->directiveHasArgument('builder')) {
            return $this->getModelClass()::query();
        }

        $builderResolver = $this->getResolverFromArgument('builder');

        $builder = $builderResolver($root, $args, $context, $resolveInfo);

        assert(
            $builder instanceof Builder || $builder instanceof ScoutBuilder,
            "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Scout Builder, Query Builder or Relation.",
        );

        return $builder;
    }
}
