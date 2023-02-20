<?php

namespace Nuwave\Lighthouse\Tracing;

use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TracingDirective extends BaseDirective implements FieldMiddleware
{
    public function __construct(
        protected Tracing $tracing
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Do not use this directive directly, it is automatically added to the schema
when using the tracing extension.
"""
directive @tracing on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(fn (callable $previousResolver) => function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
            $start = $this->tracing->timestamp();
            $result = $previousResolver($root, $args, $context, $resolveInfo);
            $end = $this->tracing->timestamp();

            Resolved::handle($result, function () use ($resolveInfo, $start, $end): void {
                $this->tracing->record($resolveInfo, $start, $end);
            });

            return $result;
        });
    }
}
