<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

/**
 * Provide a field resolver in case no resolver directive is defined for a field.
 *
 * @api
 */
interface ProvidesResolver
{
    public function provideResolver(FieldValue $fieldValue): \Closure;
}
