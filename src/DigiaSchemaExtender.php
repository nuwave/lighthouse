<?php


namespace Nuwave\Lighthouse;


use Digia\GraphQL\Language\Node\DocumentNode;
use Digia\GraphQL\Schema\Extension\ExtensionContext;
use Digia\GraphQL\Schema\Extension\SchemaExtender as BaseDigiaSchemaExtender;
use Digia\GraphQL\Schema\Schema;

class DigiaSchemaExtender extends BaseDigiaSchemaExtender
{
    public function createExtensionContext(Schema $schema, DocumentNode $document) : ExtensionContext
    {
        return $this->createContext($schema, $document, null, []);
    }
}