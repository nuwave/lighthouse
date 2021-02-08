<?php

namespace Nuwave\Lighthouse;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL as GraphQLBase;
use GraphQL\Server\Helper;
use GraphQL\Server\OperationParams;
use GraphQL\Server\RequestError;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Laragraph\Utils\RequestParser;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Execution\ErrorPool;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\SchemaBuilder;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules;
use Nuwave\Lighthouse\Support\Utils as LighthouseUtils;

class GraphQL
{
    /**
     * @var \GraphQL\Type\Schema
     */
    protected $executableSchema;

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
     * @var \Nuwave\Lighthouse\Schema\AST\ASTBuilder
     */
    protected $astBuilder;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

    /**
     * @var \Nuwave\Lighthouse\Execution\ErrorPool
     */
    protected $errorPool;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\ProvidesValidationRules
     */
    protected $providesValidationRules;

    public function __construct(
        SchemaBuilder $schemaBuilder,
        Pipeline $pipeline,
        EventDispatcher $eventDispatcher,
        ASTBuilder $astBuilder,
        CreatesContext $createsContext,
        ErrorPool $errorPool,
        ProvidesValidationRules $providesValidationRules
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->pipeline = $pipeline;
        $this->eventDispatcher = $eventDispatcher;
        $this->astBuilder = $astBuilder;
        $this->createsContext = $createsContext;
        $this->errorPool = $errorPool;
        $this->providesValidationRules = $providesValidationRules;
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function executeRequest(Request $request, RequestParser $requestParser, Helper $graphQLHelper): array
    {
        $operationParams = $requestParser->parseRequest($request);

        return LighthouseUtils::applyEach(
            /**
             * @return array<string, mixed>
             */
            function (OperationParams $operationParams) use ($graphQLHelper): array {
                $errors = $graphQLHelper->validateOperationParams($operationParams);

                if (count($errors) > 0) {
                    $errors = array_map(
                        static function (RequestError $err): Error {
                            return Error::createLocatedError($err, null, null);
                        },
                        $errors
                    );

                    return $this->applyDebugSettings(
                        new ExecutionResult(null, $errors)
                    );
                }

                return $this->executeOperation($operationParams);
            },
            $operationParams
        );
    }

    /**
     * Run a single GraphQL operation against the schema and get a result.
     *
     * @return array<string, mixed>
     */
    public function executeOperation(OperationParams $params): array
    {
        $result = $this->executeQuery(
            $params->query,
            $this->createsContext->generate(
                app('request')
            ),
            $params->variables,
            null,
            $params->operation
        );

        return $this->applyDebugSettings($result);
    }

    /**
     * Apply the debug settings from the config and get the result as an array.
     *
     * @return array<string, mixed>
     */
    public function applyDebugSettings(ExecutionResult $result): array
    {
        // If debugging is set to false globally, do not add GraphQL specific
        // debugging info either. If it is true, then we fetch the debug
        // level from the Lighthouse configuration.
        return $result->toArray(
            config('app.debug')
                ? (int) config('lighthouse.debug')
                : DebugFlag::NONE
        );
    }

    /**
     * Execute a GraphQL query on the Lighthouse schema and return the raw result.
     *
     * To render the @see ExecutionResult, you will probably want to call `->toArray($debug)` on it,
     * with $debug being a combination of flags in @see \GraphQL\Error\DebugFlag
     *
     * @param  string|\GraphQL\Language\AST\DocumentNode  $query
     * @param  array<mixed>|null  $variables
     * @param  mixed|null  $rootValue
     */
    public function executeQuery(
        $query,
        GraphQLContext $context,
        ?array $variables = [],
        $rootValue = null,
        ?string $operationName = null
    ): ExecutionResult {
        // Building the executable schema might take a while to do,
        // so we do it before we fire the StartExecution event.
        // This allows tracking the time for batched queries independently.
        $this->prepSchema();

        $this->eventDispatcher->dispatch(
            new StartExecution
        );

        $result = GraphQLBase::executeQuery(
            $this->executableSchema,
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

        $result->setErrorsHandler(
            function (array $errors, callable $formatter): array {
                // User defined error handlers, implementing \Nuwave\Lighthouse\Execution\ErrorHandler
                // This allows the user to register multiple handlers and pipe the errors through.
                $handlers = [];
                foreach (config('lighthouse.error_handlers', []) as $handlerClass) {
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
            }
        );

        // Allow listeners to manipulate the result after each resolved query
        $this->eventDispatcher->dispatch(
            new ManipulateResult($result)
        );

        $this->cleanUp();

        return $result;
    }

    /**
     * Ensure an executable GraphQL schema is present.
     */
    public function prepSchema(): Schema
    {
        if ($this->executableSchema === null) {
            $this->executableSchema = $this->schemaBuilder->build(
                $this->astBuilder->documentAST()
            );
        }

        return $this->executableSchema;
    }

    /**
     * Clean up after executing a query.
     */
    protected function cleanUp(): void
    {
        BatchLoaderRegistry::forgetInstances();
        $this->errorPool->clear();

        // TODO remove in v6
        BatchLoader::forgetInstances();
    }
}
