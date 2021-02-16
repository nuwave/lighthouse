<?php

namespace Nuwave\Lighthouse\Validation;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\Utils\FieldPath;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class ValidateDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validationFactory;

    public function __construct(ValidationFactory $validationFactory)
    {
        $this->validationFactory = $validationFactory;
    }

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
        $fieldValue->addArgumentSetTransformer(function (ArgumentSet $argumentSet, ResolveInfo $resolveInfo): ArgumentSet {
            $rulesGatherer = new RulesGatherer($argumentSet);

            $validator = $this->validationFactory->make(
                $argumentSet->toArray(),
                $rulesGatherer->rules,
                $rulesGatherer->messages,
                $rulesGatherer->attributes
            );

            if ($validator->fails()) {
                $path = FieldPath::withoutLists($resolveInfo->path);

                throw new ValidationException("Validation failed for the field [$path].", $validator);
            }

            return $argumentSet;
        });

        return $next($fieldValue);
    }
}
