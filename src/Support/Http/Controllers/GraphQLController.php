<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use GraphQL\Executor\ExecutionResult;
use Nuwave\Lighthouse\Schema\Context;
use Nuwave\Lighthouse\Schema\MiddlewareRegistry;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLController extends Controller
{
    /** @var GraphQL */
    protected $graphQL;

    /** @var bool */
    protected $batched;

    /**
     * Inject middleware into request.
     *
     * @param Request            $request
     * @param ExtensionRegistry  $extensionRegistry
     * @param MiddlewareRegistry $middlewareRegistry
     * @param GraphQL            $graphQL
     */
    public function __construct(
        Request $request,
        ExtensionRegistry $extensionRegistry,
        MiddlewareRegistry $middlewareRegistry,
        GraphQL $graphQL
    ) {
        $this->graphQL = $graphQL;
        $this->batched = isset($request[0]);

        $extensionRegistry->requestDidStart(new ExtensionRequest($request));

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
        $user = app()->bound('auth') ? auth()->user() : null;
        $context = new Context($request, $user);

        if ($this->batched) {
            $data = $this->graphQL->executeBatchedQueries(
                $request->toArray(),
                $context
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
                new Context($request, $user),
                $variables
            )->toArray($debug)
        );
    }

    /**
     * @param Request $request
     * @param string  $query
     * @param array   $variables
     *
     * @return ExecutionResult
     */
    protected function execute(Request $request, string $query, array $variables): ExecutionResult
    {
        $user = app()->bound('auth') ? auth()->user() : null;

        return $this->graphQL->executeQuery(
            $query,
            new Context($request, $user),
            $variables
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
