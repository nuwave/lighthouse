<?php

namespace Nuwave\Lighthouse;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\SyntaxError;
use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Language\Parser;
use GraphQL\Server\Helper as GraphQLHelper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\EndExecution;
use Nuwave\Lighthouse\Events\EndOperationOrOperations;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartOperationOrOperations;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Execution\ErrorPool;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;
use Nuwave\Lighthouse\Support\Utils as LighthouseUtils;

/**
 * The main entrypoint to start and end GraphQL execution.
 */
class GraphQL
{
    /**
     * @var \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    protected $schemaBuilder;

    /**
     * @var \Illuminate\Pipeline\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $eventDispatcher;

    /**
     * @var \Nuwave\Lighthouse\Execution\ErrorPool
     */
    protected $errorPool;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules
     */
    protected $providesValidationRules;

    /**
     * @var \GraphQL\Server\Helper
     */
    protected $graphQLHelper;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $configRepository;

    /**
     * Lazily initialized.
     *
     * @var \Closure(
     *   array<\GraphQL\Error\Error> $errors,
     *   callable(\GraphQL\Error\Error $error): ?array<string, mixed>
     * ): array<string, mixed>
     */
    protected $errorsHandler;

    public function __construct(
        SchemaBuilder $schemaBuilder,
        Pipeline $pipeline,
        EventDispatcher $eventDispatcher,
        ErrorPool $errorPool,
        ProvidesValidationRules $providesValidationRules,
        GraphQLHelper $graphQLHelper,
        ConfigRepository $configRepository
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->pipeline = $pipeline;
        $this->eventDispatcher = $eventDispatcher;
        $this->errorPool = $errorPool;
        $this->providesValidationRules = $providesValidationRules;
        $this->graphQLHelper = $graphQLHelper;
        $this->configRepository = $configRepository;
    }

    /**
     * Run one ore more GraphQL operations against the schema.
     *
     * @param  \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>  $operationOrOperations
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function executeOperationOrOperations($operationOrOperations, GraphQLContext $context): array
    {
        $this->eventDispatcher->dispatch(
            new StartOperationOrOperations($operationOrOperations)
        );

        $resultOrResults = LighthouseUtils::applyEach(
            /**
             * @return array<string, mixed>
             */
            function (OperationParams $operationParams) use ($context): array {
                return $this->executeOperation($operationParams, $context);
            },
            $operationOrOperations
        );

        $this->eventDispatcher->dispatch(
            new EndOperationOrOperations($resultOrResults)
        );

        return $resultOrResults;
    }

    /**
     * Run a single GraphQL operation against the schema and get a result.
     *
     * @return array<string, mixed>
     */
    public function executeOperation(OperationParams $params, GraphQLContext $context): array
    {
        $errors = $this->graphQLHelper->validateOperationParams($params);

        if (count($errors) > 0) {
            $errors = array_map(
                static function (RequestError $err): Error {
                    return Error::createLocatedError($err);
                },
                $errors
            );

            return $this->serializable(
                new ExecutionResult(null, $errors)
            );
        }

        $result = $this->executeQuery(
            $params->query,
            $context,
            $params->variables,
            null,
            $params->operation
        );

        return $this->serializable($result);
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the raw result.
     *
     * To render the @see ExecutionResult, you will probably want to call `->toArray($debug)` on it,
     * with $debug being a combination of flags in @see \GraphQL\Error\DebugFlag
     *
     * @param  string|\GraphQL\Language\AST\DocumentNode  $query
     * @param  array<string, mixed>|null  $variables
     * @param  mixed|null  $rootValue
     */
    public function executeQuery(
        $query,
        GraphQLContext $context,
        ?array $variables = [],
        $rootValue = null,
        ?string $operationName = null
    ): ExecutionResult {
        // TODO make executeQuery require a DocumentNode and move this parsing out of here
        if (is_string($query)) {
            try {
                $query = Parser::parse($query);
            } catch (SyntaxError $syntaxError) {
                return new ExecutionResult(null, [$syntaxError]);
            }
        }

        // Building the executable schema might take a while to do,
        // so we do it before we fire the StartExecution event.
        // This allows tracking the time for batched queries independently.
        $schema = $this->schemaBuilder->schema();

        $this->eventDispatcher->dispatch(
            new StartExecution($query, $variables, $operationName, $context)
        );

        $result = GraphQLBase::executeQuery(
            $schema,
            $query,
            $rootValue,
            $context,
            $variables,
            $operationName,
            null,
            $this->providesValidationRules->validationRules()
        );

        /** @var array<\Nuwave\Lighthouse\Execution\ExtensionsResponse|null> $extensionsResponses */
        $extensionsResponses = (array) $this->eventDispatcher->dispatch(
            new BuildExtensionsResponse
        );

        foreach ($extensionsResponses as $extensionsResponse) {
            if ($extensionsResponse !== null) {
                $result->extensions[$extensionsResponse->key()] = $extensionsResponse->content();
            }
        }

        foreach ($this->errorPool->errors() as $error) {
            $result->errors [] = $error;
        }

        // Allow listeners to manipulate the result after each resolved query
        $this->eventDispatcher->dispatch(
            new ManipulateResult($result)
        );

        $this->eventDispatcher->dispatch(
            new EndExecution($result)
        );

        $this->cleanUpAfterExecution();

        return $result;
    }

    protected function cleanUpAfterExecution(): void
    {
        BatchLoaderRegistry::forgetInstances();
        $this->errorPool->clear();

        // TODO remove in v6
        BatchLoader::forgetInstances();
    }

    /**
     * Convert the result to a serializable array.
     *
     * @return array<string, mixed>
     */
    public function serializable(ExecutionResult $result): array
    {
        $result->setErrorsHandler($this->errorsHandler());

        return $result->toArray($this->debugFlag());
    }

    /**
     * @return \Closure(
     *   array<\GraphQL\Error\Error> $errors,
     *   callable(\GraphQL\Error\Error $error): ?array<string, mixed>
     * ): array<string, mixed>
     */
    protected function errorsHandler(): \Closure
    {
        if (! isset($this->errorsHandler)) {
            $this->errorsHandler = function (array $errors, callable $formatter): array {
                // User defined error handlers, implementing \Nuwave\Lighthouse\Execution\ErrorHandler
                // This allows the user to register multiple handlers and pipe the errors through.
                $handlers = [];
                foreach ($this->configRepository->get('lighthouse.error_handlers', []) as $handlerClass) {
                    $handlers [] = app($handlerClass);
                }

                return (new Collection($errors))
                    ->map(function (Error $error) use ($handlers, $formatter): ?array {
                        return $this->pipeline
                            ->send($error)
                            ->through($handlers)
                            ->then(function (?Error $error) use ($formatter): ?array {
                                if ($error === null) {
                                    return null;
                                }

                                return $formatter($error);
                            });
                    })
                    ->filter()
                    ->all();
            };
        }

        return $this->errorsHandler;
    }

    protected function debugFlag(): int
    {
        // If debugging is set to false globally, do not add GraphQL specific
        // debugging info either. If it is true, then we fetch the debug
        // level from the Lighthouse configuration.
        return $this->configRepository->get('app.debug')
            ? (int) $this->configRepository->get('lighthouse.debug')
            : DebugFlag::NONE;
    }

    /**
     * Ensure an executable GraphQL schema is present.
     *
     * @deprecated
     * @see \Nuwave\Lighthouse\Schema\SchemaBuilder::schema()
     */
    public function prepSchema(): Schema
    {
        return $this->schemaBuilder->schema();
    }
}
