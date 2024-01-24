<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Async;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Nuwave\Lighthouse\GraphQL;
use Nuwave\Lighthouse\Support\Contracts\SerializesContext;

class AsyncMutation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public string $serializedContext,
        /** @var array<string, FragmentDefinitionNode> */
        public array $fragments,
        public OperationDefinitionNode $operation,
        /** @var array<string, mixed> */
        public array $variableValues,
    ) {}

    public function handle(GraphQL $graphQL, SerializesContext $serializesContext): void
    {
        $graphQL->executeParsedQuery(
            $this->query(),
            $serializesContext->unserialize($this->serializedContext),
            $this->variableValues,
            AsyncRoot::instance(),
            $this->operation->name?->value,
        );
    }

    protected function query(): DocumentNode
    {
        return new DocumentNode([
            'definitions' => new NodeList(array_merge(
                $this->fragments,
                [$this->operation],
            )),
        ]);
    }
}
