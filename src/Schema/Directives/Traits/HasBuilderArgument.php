<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives\Traits;

use Illuminate\Contracts\Database\Query\Builder;
use Laravel\Scout\Builder as ScoutBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait HasBuilderArgument
{
    /** @param array<string, mixed> $args */
    private function getBuilder(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Builder|ScoutBuilder
    {
        if (! $this->directiveHasArgument('builder')) {
            return $this->getModelClass()::query();
        }

        $builderResolver = $this->getResolverFromArgument('builder');

        $query = $builderResolver($root, $args, $context, $resolveInfo);

        assert(
            $query instanceof Builder || $query instanceof ScoutBuilder,
            "The method referenced by the builder argument of the @{$this->name()} directive on {$this->nodeName()} must return a Builder or Relation.",
        );

        return $query;
    }
}
