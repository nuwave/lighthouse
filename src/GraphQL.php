<?php declare(strict_types=1);

namespace Nuwave\Lighthouse;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Server\Helper as GraphQLHelper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventsDispatcher;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\EndExecution;
use Nuwave\Lighthouse\Events\EndOperationOrOperations;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartOperationOrOperations;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\ErrorPool;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;
use Nuwave\Lighthouse\Support\Utils as LighthouseUtils;

/**
 * The main entrypoint to GraphQL execution.
 *
 * @api
 *
 * @phpstan-import-type ErrorsHandler from \GraphQL\Executor\ExecutionResult
 * @phpstan-import-type SerializableResult from \GraphQL\Executor\ExecutionResult
 */
class GraphQL
{
    /**
     * Lazily initialized.
     *
     * @var ErrorsHandler
     */
    protected $errorsHandler;

    public function __construct(
        protected SchemaBuilder $schemaBuilder,
        protected Pipeline $pipeline,
        protected EventsDispatcher $eventDispatcher,
        protected ErrorPool $errorPool,
        protected ProvidesValidationRules $providesValidationRules,
        protected GraphQLHelper $graphQLHelper,
        protected ConfigRepository $configRepository,
    ) {}

    /**
     * Parses query and executes it.
     *
     * @api
     *
     * @param  array<string, mixed>|null  $variables
     *
     * @return array<string, mixed>
     */
    public function executeQueryString(
        string $query,
        GraphQLContext $context,
        ?array $variables = [],
        mixed $root = null,
        ?string $operationName = null,
    ): array {
        try {
            $parsedQuery = $this->parse($query);
        } catch (SyntaxError $syntaxError) {
            return $this->toSerializableArray(
                new ExecutionResult(null, [$syntaxError]),
            );
        }

        return $this->executeParsedQuery($parsedQuery, $context, $variables, $root, $operationName);
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the serializable result.
     *
     * @api
     *
     * @param  array<string, mixed>|null  $variables
     *
     * @return array<string, mixed>
     */
    public function executeParsedQuery(
        DocumentNode $query,
        GraphQLContext $context,
        ?array $variables = [],
        mixed $root = null,
        ?string $operationName = null,
    ): array {
        $result = $this->executeParsedQueryRaw($query, $context, $variables, $root, $operationName);

        return $this->toSerializableArray($result);
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the raw result.
     *
     * @param  array<string, mixed>|null  $variables
     */
    public function executeParsedQueryRaw(
        DocumentNode $query,
        GraphQLContext $context,
        ?array $variables = [],
        mixed $root = null,
        ?string $operationName = null,
    ): ExecutionResult {
        // Building the executable schema might take a while to do,
        // so we do it before we fire the StartExecution event.
        // This allows tracking the time for batched queries independently.
        $schema = $this->schemaBuilder->schema();

        $this->eventDispatcher->dispatch(
            new StartExecution($schema, $query, $variables, $operationName, $context),
        );

        $result = GraphQLBase::executeQuery(
            $schema,
            $query,
            $root,
            $context,
            $variables,
            $operationName,
            null,
            $this->providesValidationRules->validationRules(),
        );

        /** @var array<\Nuwave\Lighthouse\Execution\ExtensionsResponse|null> $extensionsResponses */
        $extensionsResponses = (array) $this->eventDispatcher->dispatch(
            new BuildExtensionsResponse($result),
        );

        foreach ($extensionsResponses as $extensionsResponse) {
            if ($extensionsResponse !== null) {
                $result->extensions[$extensionsResponse->key] = $extensionsResponse->content;
            }
        }

        foreach ($this->errorPool->errors() as $error) {
            $result->errors[] = $error;
        }

        // Allow listeners to manipulate the result after each resolved query
        $this->eventDispatcher->dispatch(
            new ManipulateResult($result),
        );

        $this->eventDispatcher->dispatch(
            new EndExecution($result),
        );

        $this->cleanUpAfterExecution();

        return $result;
    }

    /**
     * Run one or more GraphQL operations against the schema.
     *
     * @api
     *
     * @param  \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>  $operationOrOperations
     *
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function executeOperationOrOperations(OperationParams|array $operationOrOperations, GraphQLContext $context): array
    {
        $this->eventDispatcher->dispatch(
            new StartOperationOrOperations($operationOrOperations),
        );

        $resultOrResults = LighthouseUtils::mapEach(
            /** @return array<string, mixed> */
            fn (OperationParams $operationParams): array => $this->executeOperation($operationParams, $context),
            $operationOrOperations,
        );

        $this->eventDispatcher->dispatch(
            new EndOperationOrOperations($resultOrResults),
        );

        return $resultOrResults;
    }

    /**
     * Run a single GraphQL operation against the schema and get a result.
     *
     * @api
     *
     * @return array<string, mixed>
     */
    public function executeOperation(OperationParams $params, GraphQLContext $context): array
    {
        $errors = $this->graphQLHelper->validateOperationParams($params);

        if ($errors !== []) {
            $errors = array_map(
                static fn (RequestError $err): Error => Error::createLocatedError($err),
                $errors,
            );

            return $this->toSerializableArray(
                new ExecutionResult(null, $errors),
            );
        }

        $queryString = $params->query;

        try {
            if (is_string($queryString)) {
                return $this->executeQueryString(
                    $queryString,
                    $context,
                    $params->variables,
                    null,
                    $params->operation,
                );
            }

            return $this->executeParsedQuery(
                $this->loadPersistedQuery($params->queryId),
                $context,
                $params->variables,
                null,
                $params->operation,
            );
        } catch (\Throwable $throwable) {
            return $this->toSerializableArray(
                new ExecutionResult(null, [Error::createLocatedError($throwable)]),
            );
        }
    }

    /**
     * Parse the given query string into a DocumentNode.
     *
     * Caches the parsed result if the query cache is enabled in the configuration.
     *
     * @api
     */
    public function parse(string $query): DocumentNode
    {
        $cacheConfig = $this->configRepository->get('lighthouse.query_cache');

        if (! $cacheConfig['enable']) {
            return $this->parseQuery($query);
        }

        $cacheFactory = Container::getInstance()->make(CacheFactory::class);
        $store = $cacheFactory->store($cacheConfig['store']);

        $sha256 = hash('sha256', $query);

        return $store->remember(
            "lighthouse:query:{$sha256}",
            $cacheConfig['ttl'],
            fn (): DocumentNode => $this->parseQuery($query),
        );
    }

    /**
     * Convert the result to a serializable array.
     *
     * @api
     *
     * @return SerializableResult
     */
    public function toSerializableArray(ExecutionResult $result): array
    {
        $result->setErrorsHandler($this->errorsHandler());

        return $result->toArray($this->debugFlag());
    }

    /**
     * Loads persisted query from the query cache.
     *
     * @api
     */
    public function loadPersistedQuery(string $sha256hash): DocumentNode
    {
        $lighthouseConfig = $this->configRepository->get('lighthouse');
        $cacheConfig = $lighthouseConfig['query_cache'] ?? null;
        if (
            ! ($lighthouseConfig['persisted_queries'] ?? false)
            || ! ($cacheConfig['enable'] ?? false)
        ) {
            // https://github.com/apollographql/apollo-server/blob/37a5c862261806817a1d71852c4e1d9cdb59eab2/packages/apollo-server-errors/src/index.ts#L240-L248
            throw new Error(
                'PersistedQueryNotSupported',
                null,
                null,
                [],
                null,
                null,
                ['code' => 'PERSISTED_QUERY_NOT_SUPPORTED'],
            );
        }

        $cacheFactory = Container::getInstance()->make(CacheFactory::class);
        $store = $cacheFactory->store($cacheConfig['store']);

        return $store->get("lighthouse:query:{$sha256hash}")
            // https://github.com/apollographql/apollo-server/blob/37a5c862261806817a1d71852c4e1d9cdb59eab2/packages/apollo-server-errors/src/index.ts#L230-L239
            ?? throw new Error(
                'PersistedQueryNotFound',
                null,
                null,
                [],
                null,
                null,
                ['code' => 'PERSISTED_QUERY_NOT_FOUND'],
            );
    }

    /** @return ErrorsHandler */
    protected function errorsHandler(): callable
    {
        // @phpstan-ignore-next-line callable is not recognized correctly and can not be type-hinted to match
        return $this->errorsHandler ??= function (array $errors, callable $formatter): array {
            // User defined error handlers, implementing \Nuwave\Lighthouse\Execution\ErrorHandler.
            // This allows the user to register multiple handlers and pipe the errors through.
            $handlers = [];
            foreach ($this->configRepository->get('lighthouse.error_handlers', []) as $handlerClass) {
                $handlers[] = Container::getInstance()->make($handlerClass);
            }

            return (new Collection($errors))
                ->map(fn (Error $error): ?array => $this->pipeline
                    ->send($error)
                    ->through($handlers)
                    ->then(static fn (?Error $error): ?array => $error === null
                        ? null
                        : $formatter($error)))
                ->filter()
                ->all();
        };
    }

    protected function debugFlag(): int
    {
        return $this->configRepository->get('app.debug')
            // If Laravel debugging is enabled, we fetch the debug level from the Lighthouse configuration.
            ? (int) $this->configRepository->get('lighthouse.debug')
            // If Laravel debugging is disabled, do not add GraphQL specific debugging info either.
            : DebugFlag::NONE;
    }

    protected function cleanUpAfterExecution(): void
    {
        BatchLoaderRegistry::forgetInstances();
        FieldValue::clear();
        $this->errorPool->clear();
    }

    protected function parseQuery(string $query): DocumentNode
    {
        return Parser::parse($query, [
            'noLocation' => ! $this->configRepository->get('lighthouse.parse_source_location'),
        ]);
    }
}
