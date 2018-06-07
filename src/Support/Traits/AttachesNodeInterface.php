<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;

trait AttachesNodeInterface
{
    /**
     * @param ObjectTypeDefinitionNode $objectType
     * @param DocumentAST $documentAST
     * @return DocumentAST
     * @throws \Exception
     */
    protected function attachNodeInterfaceToObjectType(ObjectTypeDefinitionNode $objectType, DocumentAST $documentAST)
    {
        $objectType->interfaces = array_merge($objectType->interfaces, [Parser::parseType('Node')]);

        $objectType = DocumentAST::addFieldToObjectType($objectType, DocumentAST::parseFieldDefinition('_id: ID!'));

        return $documentAST->setObjectType($objectType);
    }
}
