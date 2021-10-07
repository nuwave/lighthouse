<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

/**
 * TODO implement this interface in resolver directives where it makes sense, e.g. @paginate.
 * This can only be done in v6, as this can be a breaking change.
 */
interface ComplexityResolverDirective extends Directive
{
    /**
     * Return a callable to use for calculating the complexity of a field.
     *
     * @return callable(int $childrenComplexity, array<string, mixed> $args): int
     */
    public function complexityResolver(FieldValue $fieldValue): callable;
}
