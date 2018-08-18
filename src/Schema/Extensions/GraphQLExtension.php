<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;

abstract class GraphQLExtension implements \JsonSerializable
{
    /**
     * Manipulate the schema.
     *
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(DocumentAST $current, DocumentAST $original): DocumentAST
    {
        return $current;
    }

    /**
     * Handle request start.
     *
     * @param ExtensionRequest $request
     */
    public function requestDidStart(ExtensionRequest $request)
    {
        return;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Extension name.
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Format extension output.
     *
     * @return array
     */
    abstract public function toArray(): array;
}
