<?php

namespace Nuwave\Lighthouse\Support\Http\Controllers;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\GraphQL;
use Illuminate\Routing\Controller;
use GraphQL\Executor\ExecutionResult;
use Symfony\Component\HttpFoundation\Response;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLResponse;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRequest;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

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
     * Inject middleware into request.
     *
     * @param  \Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry  $extensionRegistry
     * @param  \Nuwave\Lighthouse\GraphQL  $graphQL
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesContext  $createsContext
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLResponse  $graphQLResponse
     * @return void
     */
    public function __construct(
        ExtensionRegistry $extensionRegistry,
        GraphQL $graphQL,
        CreatesContext $createsContext,
        GraphQLResponse $graphQLResponse
    ) {
        $this->graphQL = $graphQL;
        $this->extensionRegistry = $extensionRegistry;
        $this->createsContext = $createsContext;
        $this->graphQLResponse = $graphQLResponse;
    }

    /**
     * Execute GraphQL query.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function query(Request $request): Response
    {
        // If the request is a 0-indexed array and the request isn't a multipart-request,
        // we know we are dealing with a batched query
        $batched = isset($request->toArray()[0]) && $this->isMultipartRequest($request) == false &&
            config('lighthouse.batched_queries', true);
        $context = $this->createsContext->generate($request);

        $this->extensionRegistry->requestDidStart(
            new ExtensionRequest($request, $context, $batched)
        );

        $response = $batched
            ? $this->executeBatched($request, $context)
            : $this->execute($request, $context);

        return $this->graphQLResponse->create(
            $this->extensionRegistry->willSendResponse($response)
        );
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @return mixed[]
     */
    protected function execute(Request $request, GraphQLContext $context): array
    {
        $operations = json_decode($request->input('operations'));

        $query = optional($operations)->query ?: $request->input('query', '');
        $variables = (array) optional($operations)->variables ?: $request->input('variables', []);
        $operationName = optional($operations)->operationName ?: $request->input('operationName');

        $variables = $this->mapUploadedFiles($request, $this->ensureVariablesAreArray($variables));

        return $this->graphQL->executeQuery(
            $query,
            $context,
            $variables,
            null,
            $operationName

        )->toArray(
            $this->getDebugSetting()
        );
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Nuwave\Lighthouse\Support\Contracts\GraphQLContext  $context
     * @return mixed[]
     */
    protected function executeBatched(Request $request, GraphQLContext $context): array
    {
        $data = $this->graphQL->executeBatchedQueries(
            $request->toArray(),
            $context
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
     * @param  mixed  $variables
     * @return mixed[]
     */
    protected function ensureVariablesAreArray($variables): array
    {
        if (is_string($variables)) {
            return json_decode($variables, true) ?? [];
        }

        return $variables ?? [];
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

    /**
     * Maps uploaded files to the variables array.
     *
     * @param Request $request
     * @param array $variables
     * @return array
     */
    protected function mapUploadedFiles(Request $request, array $variables): array
    {
        if ($this->isMultipartRequest($request)) {
            $map = json_decode($request->input('map'), true);

            // TODO Throw exception if no map defined.

            foreach ($map as $fileKey => $locations) {
                foreach ($locations as $location) {
                    $items = &$variables;
                    $location = preg_replace('/variables./', '', $location, 1);
                    $location = explode('.', $location);
                    foreach ($location as $key) {
                        if (! isset($items[$key]) || !is_array($items[$key])) {
                            $items[$key] = [];
                        }
                        $items = &$items[$key];
                    }

                    $items = $request->file($fileKey);
                }
            }
        }

        return $variables;
    }

    /**
     * Is the request a multipart-request?
     *
     * @param Request $request
     * @return bool
     */
    protected function isMultipartRequest(Request $request): bool
    {
        $contentType = $request->header('content-type') ?? '';

        return mb_stripos($contentType, 'multipart/form-data') !== false;
    }
}
