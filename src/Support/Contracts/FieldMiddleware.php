<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;

interface FieldMiddleware
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name();

    /**
     * Resolve the field directive.
     *
     * @param FieldDefinitionNode $field
     * @param Closure             $resolver
     *
     * @return Closure
     */
    public function handle(FieldDefinitionNode $field, Closure $resolver);
}
