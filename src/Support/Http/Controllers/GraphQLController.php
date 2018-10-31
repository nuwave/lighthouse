<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use GraphQL\Executor\ExecutionResult;
use Nuwave\Lighthouse\Exceptions\ParseException;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class GraphQLController extends Controller
{
    /** @var GraphQL */
    protected $graphQL;

    /** @var CreatesContext */
    protected $createsContext;

    /** @var ExtensionRegistry */
    protected $extensionRegistry;

    /**
     * Inject middleware into request.
     *
     * @param ExtensionRegistry $extensionRegistry
     * @param GraphQL           $graphQL
     * @param CreatesContext    $createsContext
     */
    public function __construct(
        ExtensionRegistry $extensionRegistry,
        GraphQL $graphQL,
        CreatesContext $createsContext
    ) {
        $this->graphQL = $graphQL;
        $this->extensionRegistry = $extensionRegistry;
        $this->createsContext = $createsContext;
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
		// If the request is a 0-indexed array, we know we are dealing with a batched query
        $batched = isset($request->toArray()[0]) && config('lighthouse.batched_queries', true);

        $this->extensionRegistry->requestDidStart(
            new ExtensionRequest($request, $batched)
        );

        $response = $batched
            ? $this->executeBatched($request)
            : $this->execute($request);

        return response(
            $this->extensionRegistry->willSendResponse($response)
        );
    }

    /**
     * @param Request $request
     *
     * @throws DirectiveException
     * @throws ParseException
     *
     * @return array
     */
    protected function execute(Request $request)
    {
        return $this->graphQL->executeQuery(
            $request->input('query', ''),
            $this->createsContext->generate($request),
            $this->ensureVariablesAreArray(
                $request->input('variables', [])
            )
        )->toArray(
            $this->getDebugSetting()
        );
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    protected function executeBatched(Request $request)
    {
        $data = $this->graphQL->executeBatchedQueries(
            $request->toArray(),
            $this->createsContext->generate($request)
        );

        return array_map(
            function (ExecutionResult $result) {
                return $result->toArray(
                    $this->getDebugSetting()
                );
            },
            $data
        );
    }

    /**
     * @param mixed $variables
     *
     * @return array
     */
    protected function ensureVariablesAreArray($variables): array
    {
        return is_string($variables)
            ? json_decode($variables, true)
            : is_null($variables) ? [] : $variables;
    }

    /**
     * Get the GraphQL debug setting.
     *
     * @return int|bool
     */
    protected function getDebugSetting()
    {
        // If debugging is set to false globally, do not add GraphQL specific
        // debugging info either. If it is true, then we fetch the debug
        // level from the Lighthouse configuration.
        return config('app.debug')
            ? config('lighthouse.debug')
            : false;
    }
}
