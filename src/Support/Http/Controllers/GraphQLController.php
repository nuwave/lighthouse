<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use GraphQL\Executor\ExecutionResult;
use Symfony\Component\HttpFoundation\Response;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;

class GraphQLController extends Controller
{
    /**
     * @var \Nuwave\Lighthouse\GraphQL
     */
    protected $graphQL;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

    /**
     * @var \Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry
     */
    protected $extensionRegistry;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLResponse
     */
    protected $graphQLResponse;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventsDispatcher;

    /**
     * Inject middleware into request.
     *
     * @param  \Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry  $extensionRegistry
     * @param  \Nuwave\Lighthouse\GraphQL  $graphQL
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesContext  $createsContext
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLResponse  $graphQLResponse
     * @param  \Illuminate\Contracts\Events\Dispatcher  $eventsDispatcher
     * @return void
     */
    public function __construct(
        ExtensionRegistry $extensionRegistry,
        GraphQL $graphQL,
        CreatesContext $createsContext,
        GraphQLResponse $graphQLResponse,
        EventsDispatcher $eventsDispatcher
    ) {
        $this->graphQL = $graphQL;
        $this->extensionRegistry = $extensionRegistry;
        $this->createsContext = $createsContext;
        $this->graphQLResponse = $graphQLResponse;
        $this->eventsDispatcher = $eventsDispatcher;
    }

    /**
     * Execute GraphQL query.
     *
     * @param  \Nuwave\Lighthouse\Execution\GraphQLRequest  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function query(GraphQLRequest $request)
    {
        $this->eventsDispatcher->dispatch(
            new StartRequest()
        );

        $response = $request->isBatched()
            ? $this->executeBatched($request)
            : $this->graphQL->executeRequest($request);

        return $this->graphQLResponse->create(
            $this->extensionRegistry->willSendResponse($response)
        );
    }

    /**
     * Loop through the individual batched queries and collect the results.
     *
     * @param  \Nuwave\Lighthouse\Execution\GraphQLRequest  $request
     * @return mixed[]
     */
    protected function executeBatched(GraphQLRequest $request): array
    {
        $results = [];

        do {
            $results[] = $this->graphQL->executeRequest($request);
        } while ($request->advanceBatchIndex());

        return $results;
    }
}
