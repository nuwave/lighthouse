<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Type\Definition\ScalarType;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgSanitizerDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Utils;

class ConvertEmptyStringsToNullDirective extends BaseDirective implements ArgSanitizerDirective, ArgDirective, FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Replaces incoming empty strings `""` with `null`.

When used upon fields, empty strings for non-nullable inputs will pass unchanged.
Only explicitly placing this on non-nullable inputs will force the conversion.
"""
directive @convertEmptyStringsToNull on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function sanitize(mixed $argumentValue): mixed
    {
        return Utils::mapEachRecursive(
            fn (mixed $value): mixed => $value instanceof ArgumentSet
                ? $this->transformArgumentSet($value)
                : $this->transformLeaf($value),
            $argumentValue,
        );
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->addArgumentSetTransformer(fn (ArgumentSet $argumentSet): ArgumentSet => $this->transformArgumentSet($argumentSet));
    }

    protected function transformArgumentSet(ArgumentSet $argumentSet): ArgumentSet
    {
        foreach ($argumentSet->arguments as $argument) {
            $namedType = $argument->namedType();
            $argumentValue = $argument->value;

            $isNullableStringType = $namedType !== null
                && $namedType->name === ScalarType::STRING
                && ! $namedType->nonNull;
            if ($isNullableStringType || $argumentValue instanceof ArgumentSet) {
                $argument->value = $this->sanitize($argumentValue);
            }
        }

        return $argumentSet;
    }

    /**
     * @param  mixed  $value  The client given value
     *
     * @return mixed The transformed value
     */
    protected function transformLeaf(mixed $value): mixed
    {
        return $value === ''
            ? null
            : $value;
    }
}
