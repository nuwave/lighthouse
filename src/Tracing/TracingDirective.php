<?php

namespace Nuwave\Lighthouse\Tracing;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Resolved;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TracingDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Nuwave\Lighthouse\Tracing\Tracing
     */
    protected $tracing;

    public function __construct(Tracing $tracing)
    {
        $this->tracing = $tracing;
    }

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

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        // Make sure this middleware is applied last
        $fieldValue = $next($fieldValue);

        $previousResolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
            $start = $this->tracing->timestamp();
            $result = $previousResolver($root, $args, $context, $resolveInfo);
            $end = $this->tracing->timestamp();

            Resolved::handle($result, function () use ($resolveInfo, $start, $end): void {
                $this->tracing->record($resolveInfo, $start, $end);
            });

            return $result;
        });

        return $fieldValue;
    }
}
