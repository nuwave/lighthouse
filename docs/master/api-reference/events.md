# Events

This reference lists the events that Lighthouse dispatches during a request in order
of execution.

All events reside in the namespace `\Nuwave\Lighthouse\Events`.

## StartRequest

```php
<?php

namespace Nuwave\Lighthouse\Events;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fires right after a request reaches the GraphQLController.
 *
 * Can be used for logging or for measuring and monitoring
 * the time a request takes to resolve.
 *
 * @see \Nuwave\Lighthouse\Support\Http\Controllers\GraphQLController
 */
class StartRequest
{
    /**
     * HTTP request instance.
     *
     * @var \Illuminate\Http\Request
     */
    public $request;

    /**
     * The point in time when the request started.
     *
     * @var \Illuminate\Support\Carbon
     */
    public $moment;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->moment = Carbon::now();
    }
}
```

## BuildSchemaString

```php
<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires before building the AST from the user-defined schema string.
 *
 * Listeners may return a schema string,
 * which is added to the user schema.
 *
 * Only fires once if schema caching is active.
 */
class BuildSchemaString
{
    /**
     * The root schema that was defined by the user.
     *
     * @var string
     */
    public $userSchema;

    public function __construct(string $userSchema)
    {
        $this->userSchema = $userSchema;
    }
}
```

## ManipulateAST

```php
<?php

namespace Nuwave\Lighthouse\Events;

use Nuwave\Lighthouse\Schema\AST\DocumentAST;

/**
 * Fires after the AST was built but before the executable schema is built.
 *
 * Listeners may mutate the $documentAST and make programmatic
 * changes to the schema.
 *
 * Only fires once if schema caching is active.
 */
class ManipulateAST
{
    /**
     * The AST that can be manipulated.
     *
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public $documentAST;

    public function __construct(DocumentAST &$documentAST)
    {
        $this->documentAST = $documentAST;
    }
}
```

## RegisterDirectiveNamespaces

```php
<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires when the directive factory is constructed.
 *
 * Listeners may return one or more strings that are used as the base
 * namespace for locating directives.
 *
 * @see \Nuwave\Lighthouse\Schema\DirectiveLocator
 */
class RegisterDirectiveNamespaces
{
    //
}
```

## StartExecution

```php
<?php

namespace Nuwave\Lighthouse\Events;

use Illuminate\Support\Carbon;

/**
 * Fires right before resolving an individual query.
 *
 * Might happen multiple times in a single request if
 * query batching is used.
 */
class StartExecution
{
    /**
     * The point in time when the query execution started.
     *
     * @var \Illuminate\Support\Carbon
     */
    public $moment;

    public function __construct()
    {
        $this->moment = Carbon::now();
    }
}
```

## BuildExtensionsResponse

```php
<?php

namespace Nuwave\Lighthouse\Events;

/**
 * Fires after a query was resolved.
 *
 * Listeners of this event may return an instance of
 * @see \Nuwave\Lighthouse\Execution\ExtensionsResponse
 * that is then added to the response.
 */
class BuildExtensionsResponse
{
    //
}
```

```php
<?php

namespace Nuwave\Lighthouse\Execution;

/**
 * May be returned from listeners of the event:
 * @see \Nuwave\Lighthouse\Events\BuildExtensionsResponse
 */
class ExtensionsResponse
{
    /**
     * Will be used as the key in the response map.
     *
     * @var string
     */
    protected $key;

    /**
     * JSON-encodable content of the extension.
     *
     * @var mixed
     */
    protected $content;

    public function __construct(string $key, $content)
    {
        $this->key = $key;
        $this->content = $content;
    }

    /**
     * Return the key of the extension.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Return the JSON-encodable content of the extension.
     *
     * @return mixed
     */
    public function content()
    {
        return $this->content;
    }
}
```

## ManipulateResult

```php
<?php

namespace Nuwave\Lighthouse\Events;

use GraphQL\Executor\ExecutionResult;

/**
 * Fires after resolving each individual query.
 *
 * This gives listeners an easy way to manipulate the query
 * result without worrying about batched execution.
 */
class ManipulateResult
{
    /**
     * The result of resolving an individual query.
     *
     * @var \GraphQL\Executor\ExecutionResult
     */
    public $result;

    public function __construct(ExecutionResult &$result)
    {
        $this->result = $result;
    }
}
```
