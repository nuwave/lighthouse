<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;

abstract class GraphQLExtension implements \JsonSerializable
{
    /**
     * Manipulate the schema.
     *
     * @param DocumentAST $documentAST
     *
     * @return DocumentAST
     */
    public function manipulateSchema(DocumentAST $documentAST)
    {
        return $documentAST;
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
     * Handle batch request start.
     *
     * @param int index
     */
    public function batchedQueryDidStart($index)
    {
        return;
    }

    /**
     * Handle batch request end.
     *
     * @param int $index
     */
    public function batchedQueryDidEnd($index)
    {
        return;
    }

    /**
     * The extension name controls under which key the extensions shows up in the result.
     *
     * @return string
     */
    abstract public static function name();
}
