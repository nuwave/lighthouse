<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

/**
 * @phpstan-import-type ComplexityFn from \GraphQL\Type\Definition\FieldDefinition
 */
interface ComplexityResolverDirective extends Directive
{
    /**
     * Return a callable to use for calculating the complexity of a field.
     *
     * @return ComplexityFn
     */
    public function complexityResolver(FieldValue $fieldValue): callable;
}
