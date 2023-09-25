# Events

All events reside in the namespace `\Nuwave\Lighthouse\Events`.

## Lifecycle Events

Lighthouse dispatches the following order of events during a request.

### StartRequest

```php
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fires right after a request reaches the GraphQLController.
 *
 * Can be used for logging or for measuring and monitoring
 * the time a request takes to resolve.
 *
 * @see \Nuwave\Lighthouse\Http\GraphQLController
 */
class StartRequest
{
    /**
     * The point in time when the request started.
     */
    public Carbon $moment;

    public function __construct(
        /**
         * The request sent from the client.
         */
        public Request $request
    ) {
        $this->moment = Carbon::now();
    }
}
```

### StartOperationOrOperations

```php
use GraphQL\Server\OperationParams;

/**
 * Fires after receiving the parsed operation (single query) or operations (batched query).
 */
class StartOperationOrOperations
{
    public function __construct(
        /**
         * One or multiple parsed GraphQL operations.
         *
         * @var \GraphQL\Server\OperationParams|array<int, \GraphQL\Server\OperationParams>
         */
        public OperationParams|array $operationOrOperations
    ) {}
}
```

### BuildSchemaString

```php
/**
 * Fires before building the AST from the user-defined schema string.
 *
 * Listeners may return a schema string, which is added to the user schema.
 *
 * Only fires once if schema caching is active.
 */
class BuildSchemaString
{
    public function __construct(
        /**
         * The root schema that was defined by the user.
         */
        public string $userSchema
    ) {}
}
```

### RegisterDirectiveNamespaces

```php
/**
 * Fires when the schema is constructed and the first directive is encountered.
 *
 * Listeners may return namespaces in the form of either:
 * - a single string
 * - an iterable of multiple strings
 * The returned namespaces will be used as the search base for locating directives.
 *
 * @see \Nuwave\Lighthouse\Schema\DirectiveLocator::namespaces()
 */
class RegisterDirectiveNamespaces {}
```

### ManipulateAST

```php
use Nuwave\Lighthouse\Schema\AST\DocumentAST;

/**
 * Fires after the AST was built but before the executable schema is built.
 *
 * Listeners may mutate the $documentAST and make programmatic changes to the schema.
 *
 * Only fires once if schema caching is active.
 */
class ManipulateAST
{
    public function __construct(
        /**
         * The AST that can be manipulated.
         */
        public DocumentAST &$documentAST
    ) {}
}
```

### StartExecution

```php
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Fires right before resolving a single operation.
 *
 * Might happen multiple times in a single request if batching is used.
 */
class StartExecution
{
    /**
     * The point in time when the query execution started.
     */
    public Carbon $moment;

    public function __construct(
        /**
         * The parsed schema.
         */
        public Schema $schema,

        /**
         * The client given parsed query string.
         */
        public DocumentNode $query,

        /**
         * The client given variables, neither validated nor transformed.
         *
         * @var array<string, mixed>|null
         */
        public ?array $variables,

        /**
         * The client given operation name.
         */
        public ?string $operationName,

        /**
         * The context for the operation.
         */
        public GraphQLContext $context,
    ) {
        $this->moment = Carbon::now();
    }
}
```

### BuildExtensionsResponse

```php
/**
 * Fires after a query was resolved.
 *
 * Listeners may return a @see \Nuwave\Lighthouse\Execution\ExtensionsResponse
 * to include in the response.
 */
class BuildExtensionsResponse {}
```

```php
namespace Nuwave\Lighthouse\Execution;

/**
 * May be returned from listeners of @see \Nuwave\Lighthouse\Events\BuildExtensionsResponse.
 */
class ExtensionsResponse
{
    public function __construct(
        /**
         * Will be used as the key in the response map.
         */
        public string $key,
        /**
         * JSON-encodable content of the extension.
         */
        public mixed $content,
    ) {}
}
```

### ManipulateResult

```php
use GraphQL\Executor\ExecutionResult;

/**
 * Fires after resolving each individual query.
 *
 * This gives listeners an easy way to manipulate the query
 * result without worrying about batched execution.
 */
class ManipulateResult
{
    public function __construct(
        /**
         * The result of resolving an individual query.
         */
        public ExecutionResult &$result
    ) {}
}
```

### EndExecution

```php
use GraphQL\Executor\ExecutionResult;
use Illuminate\Support\Carbon;

/**
 * Fires after resolving a single operation.
 */
class EndExecution
{
    /**
     * The point in time when the result was ready.
     */
    public Carbon $moment;

    public function __construct(
        /**
         * The result of resolving a single operation.
         */
        public ExecutionResult $result
    ) {
        $this->moment = Carbon::now();
    }
}
```

### EndOperationOrOperations

```php
/**
 * Fires after resolving all operations.
 */
class EndOperationOrOperations
{
    public function __construct(
        /**
         * The result of either a single or multiple operations.
         *
         * @var array<string, mixed>|array<int, array<string, mixed>> $resultOrResults
         */
        public array $resultOrResults
    ) {}
}
```

### EndRequest

```php
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fires right after building the HTTP response in the GraphQLController.
 *
 * Can be used for logging or for measuring and monitoring
 * the time a request takes to resolve.
 *
 * @see \Nuwave\Lighthouse\Http\GraphQLController
 */
class EndRequest
{
    /**
     * The point in time when the response was ready.
     */
    public Carbon $moment;

    public function __construct(
        /**
         * The response that is about to be sent to the client.
         */
        public Response $response
    ) {
        $this->moment = Carbon::now();
    }
}
```

## Non-lifecycle Events

The following events happen outside the execution lifecycle.

### ValidateSchema

```php
use GraphQL\Type\Schema;

/**
 * Dispatched when php artisan lighthouse:validate-schema is called.
 *
 * Listeners should throw a descriptive error if the schema is wrong.
 */
class ValidateSchema
{
    public function __construct(
        /**
         * The final schema to validate.
         */
        public Schema $schema,
    ) {}
}
```
