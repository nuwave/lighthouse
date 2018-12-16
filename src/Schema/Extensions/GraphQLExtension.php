<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Execution\GraphQLRequest;

abstract class GraphQLExtension implements \JsonSerializable
{
    /**
     * The extension name controls under which key the extensions shows up in the result.
     *
     * @return string
     */
    abstract public static function name();

    /**
     * The query for a request is about to start.
     *
     * In case of a batched query, this is called multiple times.
     *
     * @param GraphQLRequest $request
     */
    public function start(GraphQLRequest $request)
    {
        // Reset the extension so it is ready to handle a new request
    }

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
     * Manipulate the GraphQL response.
     *
     * @param array    $response
     * @param \Closure $next
     *
     * @return array
     */
    public function willSendResponse(array $response, \Closure $next)
    {
        return $next($response);
    }
}
