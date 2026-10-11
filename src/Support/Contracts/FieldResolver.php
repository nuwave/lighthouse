<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

/**
 * @phpstan-import-type Resolver from \Nuwave\Lighthouse\Schema\Values\FieldValue
 */
interface FieldResolver extends Directive
{
    /**
     * Returns a field resolver function.
     *
     * @return Resolver
     */
    public function resolveField(FieldValue $fieldValue): callable;
}
