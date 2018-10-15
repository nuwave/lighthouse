<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use GraphQL\Executor\ExecutionResult;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLController extends Controller
{
    /** @var GraphQL */
    protected $graphQL;

    /** @var CreatesContext */
    protected $createsContext;

    /** @var bool */
    protected $batched = false;

    /**
     * Inject middleware into request.
     *
     * @param Request            $request
     * @param ExtensionRegistry  $extensionRegistry
     * @param MiddlewareRegistry $middlewareRegistry
     * @param GraphQL            $graphQL
     * @param CreatesContext     $createsContext
     */
    public function __construct(
        Request $request,
        ExtensionRegistry $extensionRegistry,
        MiddlewareRegistry $middlewareRegistry,
        GraphQL $graphQL,
        CreatesContext $createsContext
    ) {
        $this->graphQL = $graphQL;
        $this->createsContext = $createsContext;

        if ($request->route()) {
            $this->batched = isset($request[0]) && config('lighthouse.batched_queries', true);

            $extensionRegistry->requestDidStart(
                new ExtensionRequest($request, $this->batched)
            );

            $graphQL->prepSchema();
            $middleware = ! $this->batched
                ? $middlewareRegistry->forRequest($request->input('query'))
                : array_reduce(
                    $request->toArray(),
                    function ($middleware, $req) use ($middlewareRegistry) {
                        $query = array_get($req, 'query', '');

                        return array_merge(
                            $middleware,
                            $middlewareRegistry->forRequest($query)
                        );
                    },
                    []
                );

            $this->middleware($middleware);
        }
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
        $debug = config('app.debug') ? config('lighthouse.debug') : false;

        if ($this->batched) {
            $data = $this->graphQL->executeBatchedQueries(
                $request->toArray(),
                $this->createsContext->generate($request)
            );

            return response(
                array_map(function (ExecutionResult $result) use ($debug) {
                    return $result->toArray($debug);
                }, $data)
            );
        }

        $query = $request->input('query', '');
        $variables = is_string($vars = $request->input('variables', []))
            ? json_decode($vars, true)
            : $vars;

        return response(
            $this->graphQL->executeQuery(
                $query,
                $this->createsContext->generate($request),
                $variables
            )->toArray($debug)
        );
    }

    /**
     * @param mixed $variables
     *
     * @return array
     */
    protected function getVariables($variables): array
    {
        return is_string($variables) ? json_decode($variables, true) : $variables;
    }
}
