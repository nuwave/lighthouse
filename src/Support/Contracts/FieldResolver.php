<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\FieldDefinitionNode;

interface FieldResolver
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
     *
     * @return \Closure
     */
    public function handle(FieldDefinitionNode $field);
}
