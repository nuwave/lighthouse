<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

trait AttachesNodeInterface
{
    /**
     * @param ObjectTypeDefinitionNode $objectType
     * @param DocumentAST              $documentAST
     *
     * @throws \Exception
     *
     * @return DocumentAST
     */
    protected function attachNodeInterfaceToObjectType(ObjectTypeDefinitionNode $objectType, DocumentAST $documentAST)
    {
        $objectType->interfaces = array_merge($objectType->interfaces, [Parser::parseType('Node')]);

        $globalIdFieldName = config('lighthouse.global_id_field', '_id');

        $globalIdFieldDefinition = PartialParser::fieldDefinition($globalIdFieldName.': ID!');
        $objectType->fields->merge([$globalIdFieldDefinition]);

        return $documentAST->setDefinition($objectType);
    }
}
