<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Support\Collection;

class QueryAST
{
    /**
     * The definitions contained in the AST of an incoming query.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $definitions;

    /**
     * @param  \GraphQL\Language\AST\DocumentNode  $documentNode
     * @return void
     */
    public function __construct(DocumentNode $documentNode)
    {
        $this->definitions = new Collection($documentNode->definitions);
    }

    /**
     * Create a new instance from a query string.
     *
     * @param  string  $query
     * @return static
     */
    public static function fromSource(string $query): self
    {
        return new static(
            Parser::parse($query)
        );
    }

    /**
     * Get all operation definitions.
     *
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\OperationDefinitionNode>
     */
    public function operationDefinitions(): Collection
    {
        return $this->definitionsByType(OperationDefinitionNode::class);
    }

    /**
     * Get all fragment definitions.
     *
     * @return \Illuminate\Support\Collection<\GraphQL\Language\AST\FragmentDefinitionNode>
     */
    public function fragmentDefinitions(): Collection
    {
        return $this->definitionsByType(FragmentDefinitionNode::class);
    }

    /**
     * Get all definitions of a given type.
     *
     * @param  string  $typeClassName
     * @return \Illuminate\Support\Collection
     */
    protected function definitionsByType(string $typeClassName): Collection
    {
        return $this->definitions
            ->filter(function (Node $node) use ($typeClassName): bool {
                return $node instanceof $typeClassName;
            });
    }
}
