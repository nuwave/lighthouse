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

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
                    $argumentSet = $resolveInfo->argumentSet;
                    $rulesGatherer = new RulesGatherer($argumentSet);

                    /**
                     * @var \Illuminate\Contracts\Validation\Factory $validationFactory
                     */
                    $validationFactory = app(ValidationFactory::class);
                    $validator = $validationFactory->make(
                        $args,
                        self::normalizeRules($rulesGatherer->rules),
                        $rulesGatherer->messages,
                        $rulesGatherer->attributes
                    );

                    if ($validator->fails()) {
                        $path = implode('.', $resolveInfo->path);

                        throw new ValidationException("Validation failed for the field [$path].", $validator);
                    }

                    return $resolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }

    /**
     * @param array<string, array<int, array<int, string>|object>> $rules
     * @return array<string, array<int, string|object>>
     */
    private static function normalizeRules(array $rules): array
    {
        $transformed = [];
        foreach ($rules as $key => $value) {
            $transformed[$key] = array_map(function ($rule) {
                if (is_array($rule)) {
                    $name = $rule[0];
                    $parameters = array_slice($rule, 1, 100);

                    if ($parameters) {
                        return $name . ':' . implode(',', $parameters);
                    }

                    return $name;
                }

                return $rule;
            }, $value);
        }

        return $transformed;
    }
}
