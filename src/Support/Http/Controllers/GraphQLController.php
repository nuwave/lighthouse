<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Schema\MiddlewareManager;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLController extends Controller
{
    /** @var string */
    private $query;
    /** @var array */
    private $variables;

    /**
     * Inject middleware into request.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->query = $request->input('query');
        $this->variables = is_string($variables = $request->input('variables'))
            ? json_decode($variables, true)
            : $variables;

        resolve(ExtensionRegistry::class)->requestDidStart(
            new ExtensionRequest($request, $this->query, $this->variables)
        );

        graphql()->prepSchema();

        $this->middleware(
            resolve(MiddlewareManager::class)->forRequest($this->query)
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
            graphql()->executeQuery(
                $this->query,
                new Context($request, app('auth')->user()),
                $this->variables
            )->toArray($debug)
        );
    }
}
