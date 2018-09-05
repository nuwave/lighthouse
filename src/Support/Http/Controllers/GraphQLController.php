<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLController extends Controller
{
    /** @var string */
    private $query;
    /** @var array */
    private $variables;
    /** @var GraphQL */
    private $graphQL;

    /**
     * Inject middleware into request.
     *
     * @param Request $request
     * @param ExtensionRegistry $extensionRegistry
     * @param MiddlewareRegistry $middlewareRegistry
     * @param GraphQL $graphQL
     */
    public function __construct(Request $request, ExtensionRegistry $extensionRegistry, MiddlewareRegistry $middlewareRegistry, GraphQL $graphQL)
    {
        $this->graphQL = $graphQL;

        $this->query = $request->input('query');
        $this->variables = is_string($variables = $request->input('variables'))
            ? json_decode($variables, true)
            : $variables;

        $extensionRegistry->requestDidStart(
            new ExtensionRequest($request, $this->query, $this->variables)
        );

        $this->graphQL->prepSchema();

        $this->middleware(
            $middlewareRegistry->forRequest($this->query)
        );
    }

    /**
     * Execute GraphQL query.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function query(Request $request)
    {
        $debug = config('app.debug')
            ? config('lighthouse.debug')
            : false;

        return response(
            $this->graphQL
                ->executeQuery(
                    $this->query,
                    new Context($request, auth()->user()),
                    $this->variables
                )
                ->toArray($debug)
        );
    }
}
