<?php

declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution;

use GraphQL\Language\Parser;
use GraphQL\Language\AST\Node;
use GraphQL\Error\SyntaxError;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;

class QueryAST
{
    /**
     * @var Collection
     */
    protected $definitions;
    
    /**
     * @var Collection
     */
    protected $typeExtensions;
    
    /**
     * @param DocumentNode $documentNode
     */
    public function __construct(DocumentNode $documentNode)
    {
        $this->definitions = collect($documentNode->definitions);
    }
    
    /**
     * Create a new DocumentAST instance from a schema.
     *
     * @param string $schema
     *
     * @throws SyntaxError
     *
     * @return QueryAST
     */
    public static function fromSource(string $schema): QueryAST
    {
        return new static(
            Parser::parse($schema)
        );
    }

    /**
     * Get all definitions for operations.
     *
     * @return Collection
     */
    public function operationDefinitions(): Collection
    {
        return $this->definitionsByType(OperationDefinitionNode::class);
    }

    /**
     * Get all fragment definitions.
     *
     * @return Collection
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
