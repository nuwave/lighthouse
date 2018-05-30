<?php

namespace Nuwave\Lighthouse\Support\Contracts;

use GraphQL\Language\AST\DefinitionNode;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;

interface SchemaGenerator extends Directive
{

    /**
     * @param $fieldDefinition
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @return DocumentAST
     */
    public function handleSchemaGeneration($fieldDefinition, DocumentAST $current, DocumentAST $original);
}
