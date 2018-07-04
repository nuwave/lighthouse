<?php

namespace Nuwave\Lighthouse\Support\Traits;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\Parser;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

/**
 * @deprecated this trait will be removed in a future version of Lighthouse
 */
trait CanParseTypes
{
    /**
     * Parse schema to definitions.
     *
     * @param string $schema
     *
     * @return DocumentNode
     */
    public function parseSchema($schema)
    {
        return Parser::parse($schema, ['noLocation' => true]);
    }

    /**
     * Get definitions from document.
     *
     * @param DocumentNode $document
     *
     * @return \Illuminate\Support\Collection
     */
    protected function definitions(DocumentNode $document)
    {
        return collect($document->definitions);
    }

    /**
     * Get object types from document.
     *
     * @param DocumentNode $document
     *
     * @return \Illuminate\Support\Collection
     */
    protected function objectTypes(DocumentNode $document)
    {
        return $this->definitions($document)->filter(function ($def) {
            return $def instanceof ObjectTypeDefinitionNode;
        });
    }

    /**
     * Convert node to type.
     *
     * @param DocumentNode $node
     *
     * @return \GraphQL\Type\Definition\Type
     */
    protected function convertNode($node)
    {
        return (new NodeFactory)->handle(new NodeValue($node));
    }
}
