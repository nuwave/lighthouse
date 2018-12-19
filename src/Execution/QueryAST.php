<?php

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Language\Parser;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\Node;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;

class QueryAST
{
    /**
     * The definitions contained in the AST of an incoming query.
     *
     * @var Collection
     */
    protected $definitions;

    /**
     * @param DocumentNode $documentNode
     */
    public function __construct(DocumentNode $documentNode)
    {
        $this->definitions = collect($documentNode->definitions);
    }
    
    /**
     * Create a new instance from a query string.
     *
     * @param string $query
     *
     * @throws SyntaxError
     *
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
     * @return Collection<OperationDefinitionNode>
     */
    public function operationDefinitions(): Collection
    {
        return $this->definitionsByType(OperationDefinitionNode::class);
    }

    /**
     * Get all fragment definitions.
     *
     * @return Collection<FragmentDefinitionNode>
     */
    public function fragmentDefinitions(): Collection
    {
        return $this->definitionsByType(FragmentDefinitionNode::class);
    }

    /**
     * Get all definitions of a given type.
     *
     * @param string $typeClassName
     *
     * @return Collection
     */
    protected function definitionsByType(string $typeClassName): Collection
    {
        return $this->definitions
            ->filter(function (Node $node) use ($typeClassName) {
                return $node instanceof $typeClassName;
            });
    }
}
