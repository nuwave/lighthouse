<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

interface ComplexityResolverDirective extends Directive
{
    /**
     * Return a callable to use for calculating the complexity of a field.
     *
     * @return callable(int $childrenComplexity, array<string, mixed> $args): int
     */
    public function complexityResolver(FieldValue $fieldValue): callable;
}
