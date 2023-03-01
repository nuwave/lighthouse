<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Schema\Values\TypeValue;

interface TypeResolver extends Directive
{
    /**
     * Resolve a type AST to a GraphQL Type.
     */
    public function resolveNode(TypeValue $value): Type;
}
