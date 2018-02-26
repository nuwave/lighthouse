<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldDefinitionNode;

abstract class FieldDirective
{
    /**
     * Resolve the field directive.
     *
     * @param FieldDefinitionNode $field
     *
     * @return mixed
     */
    abstract public function handle(FieldDefinitionNode $field);
}
