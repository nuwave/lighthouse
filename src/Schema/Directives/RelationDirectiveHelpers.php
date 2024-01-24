<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait RelationDirectiveHelpers
{
    abstract protected function directiveArgValue(string $name, $default = null);

    abstract protected function nodeName(): string;

    /** @return array<int, string> */
    protected function scopes(): array
    {
        return $this->directiveArgValue('scopes', []);
    }

    protected function relation(): string
    {
        return $this->directiveArgValue('relation', $this->nodeName());
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @return \Closure(QueryBuilder|\Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>): void
     */
    protected function makeBuilderDecorator(mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): \Closure
    {
        return function (object $builder) use ($root, $args, $context, $resolveInfo): void {
            if ($builder instanceof Relation) {
                $builder = $builder->getQuery();
            }

            assert($builder instanceof QueryBuilder || $builder instanceof EloquentBuilder);

            $resolveInfo->enhanceBuilder(
                $builder,
                $this->scopes(),
                $root,
                $args,
                $context,
                $resolveInfo,
            );
        };
    }

    /**
     * @param  array<string, mixed>  $args
     *
     * @return array<int, int|string>
     */
    protected function qualifyPath(array $args, ResolveInfo $resolveInfo): array
    {
        // Includes the field we are loading the relation for
        $path = $resolveInfo->path;

        // In case we have no args, we can combine eager loads that are the same
        if ($args === []) {
            array_pop($path);
        }

        // Each relation must be loaded separately
        $path[] = $this->relation();

        // Scopes influence the result of the query
        return array_merge($path, $this->scopes());
    }
}
