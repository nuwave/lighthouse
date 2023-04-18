<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\TypeValue;

interface TypeMiddleware extends Directive
{
    /** Handle a type AST as it is converted to an executable type. */
    public function handleNode(TypeValue $value): void;
}
