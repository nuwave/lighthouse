<?php

namespace Nuwave\Lighthouse\Events;

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
     * The parsed schema.
     *
     * @var \GraphQL\Type\Schema
     */
    public $schema;

    /**
     * The client given parsed query string.
     *
     * @var \GraphQL\Language\AST\DocumentNode
     */
    public $query;

    /**
     * The client given variables, neither validated nor transformed.
     *
     * @var array<string, mixed>|null
     */
    public $variables;

    /**
     * The client given operation name.
     *
     * @var string|null
     */
    public $operationName;

    /**
     * The context for the operation.
     *
     * @var \Nuwave\Lighthouse\Support\Contracts\GraphQLContext
     */
    public $context;

    /**
     * The point in time when the query execution started.
     *
     * @var \Illuminate\Support\Carbon
     */
    public $moment;

    /**
     * @param  array<string, mixed>|null  $variables
     */
    public function __construct(Schema $schema, DocumentNode $query, ?array $variables, ?string $operationName, GraphQLContext $context)
    {
        $this->schema = $schema;
        $this->query = $query;
        $this->variables = $variables;
        $this->operationName = $operationName;
        $this->context = $context;
        $this->moment = Carbon::now();
    }
}
