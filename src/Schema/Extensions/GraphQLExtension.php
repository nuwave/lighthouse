<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use GraphQL\Executor\ExecutionResult;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

abstract class GraphQLExtension implements \JsonSerializable
{
    /**
     * Manipulate the schema.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(DocumentAST $documentAST): DocumentAST
    {
        return $documentAST;
    }

    /**
     * Handle request start.
     *
     * @param  \Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest  $request
     */
    public function requestDidStart(ExtensionRequest $request)
    {
    }

    /**
     * Handle batch request start.
     *
     * @param  int  $index
     */
    public function batchedQueryDidStart(int $index)
    {
    }

    /**
     * Handle batch request end.
     *
     * @param  \GraphQL\Executor\ExecutionResult  $result
     * @param  int  $index
     */
    public function batchedQueryDidEnd(ExecutionResult $result, int $index)
    {
    }

    /**
     * Manipulate the GraphQL response.
     *
     * @param  array  $response
     * @param  \Closure  $next
     * @return array
     */
    public function willSendResponse(array $response, \Closure $next)
    {
        return $next($response);
    }

    /**
     * The extension name controls under which key the extensions shows up in the result.
     *
     * @return string
     */
    abstract public static function name(): string;
}
