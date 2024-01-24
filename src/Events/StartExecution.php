<?php declare(strict_types=1);

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
    /** The point in time when the query execution started. */
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
