<?php

namespace Nuwave\Lighthouse\Schema\Utils;


use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use Nuwave\Lighthouse\Support\Contracts\SchemaManipulator;

class ASTBuilder
{
    /**
     * @param string $schema
     *
     * @return DocumentAST
     */
    public static function generate($schema)
    {
        $document = DocumentAST::parse($schema);

        // Apply these first, they might define fields which have generator directives themselves
        $document = self::applyObjectTypeGenerators($document);
        $document = self::applyFieldGenerators($document);

        $document = self::mergeTypeExtensions($document);

        $document = self::injectNodeField($document);

        return $document;
    }

    protected static function mergeTypeExtensions(DocumentAST $document)
    {
        $document->objectTypes()->each(function (ObjectTypeDefinitionNode $objectType) use ($document) {
            $name = $objectType->name->value;

            if ($typeExtension = $document->getTypeExtension($name)) {
                /** @var NodeList $fields */
                $fields = $objectType->fields;
                $objectType->fields = $fields->merge($typeExtension->definition->fields);
                // Modify the original document by overwriting the definition with the merged one
                $document->setObjectType($objectType);
            }
        });

        return $document;
    }


    /**
     * Inject node field into Query.
     *
     * @param DocumentAST $document
     *
     * @return DocumentAST
     */
    protected static function injectNodeField(DocumentAST $document)
    {
        if (is_null(config('lighthouse.global_id_field'))) {
            return $document;
        }
//
//        if (! $query = $this->instance('Query')) {
//            return;
//        }

        $nodeQuery = DocumentAST::parseFieldDefinition('node(id: ID!): Node @field(resolver: "Nuwave\\\Lighthouse\\\Support\\\Http\\\GraphQL\\\Queries\\\NodeQuery@resolve")');
        $document->addFieldToQueryType($nodeQuery);

        return $document;
    }

    /**
     * @param DocumentAST $document
     * @return DocumentAST
     */
    protected static function applyObjectTypeGenerators(DocumentAST $document)
    {
        $originalDocument = $document;

        $extendedTypes = $document->typeExtensions()->map(function(TypeExtensionDefinitionNode $typeExtension){
            return $typeExtension->definition;
        });

        $objectTypes = $document->objectTypes()->concat($extendedTypes);

        return $objectTypes->reduce(function (DocumentAST $document, ObjectTypeDefinitionNode $objectType) use ($originalDocument) {
            $generators = directives()->generators($objectType);

            return $generators->reduce(function (DocumentAST $document, SchemaManipulator $generator) use ($originalDocument, $objectType) {
                return $generator->manipulateSchema($objectType, $document, $originalDocument);
            }, $document);
        }, $document);
    }

    /**
     * @param DocumentAST $document
     * @return DocumentAST
     */
    protected static function applyFieldGenerators(DocumentAST $document)
    {
        $originalDocument = $document;

        return $document->objectTypes()->reduce(function (DocumentAST $document, ObjectTypeDefinitionNode $objectType) use ($originalDocument) {
            return collect($objectType->fields)->reduce(function (DocumentAST $document, FieldDefinitionNode $fieldDefinition) use ($objectType, $originalDocument) {
                $generators = directives()->generators($fieldDefinition);

                return $generators->reduce(function (DocumentAST $document, SchemaManipulator $generator) use ($fieldDefinition, $objectType, $originalDocument) {
                    return $generator->manipulateSchema($fieldDefinition, $document, $originalDocument, $objectType);
                }, $document);
            }, $document);
        }, $document);
    }
}
