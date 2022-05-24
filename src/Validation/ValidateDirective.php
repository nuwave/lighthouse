<?php

namespace Nuwave\Lighthouse\Validation;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ValidateDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Run validation on a field.
"""
directive @validate on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $resolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $argumentSet = $resolveInfo->argumentSet;
            $rulesGatherer = new RulesGatherer($argumentSet);

            $validationFactory = app(ValidationFactory::class);
            assert($validationFactory instanceof ValidationFactory);

            $validator = $validationFactory->make(
                $args,
                $rulesGatherer->rules,
                $rulesGatherer->messages,
                $rulesGatherer->attributes
            );

            if ($validator->fails()) {
                $path = implode('.', $resolveInfo->path);

                throw new ValidationException("Validation failed for the field [$path].", $validator);
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });

        return $next($fieldValue);
    }
}
