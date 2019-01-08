<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

/**
 * Data wrapper around a InputValueDefinitionNode.
 *
 * The main use for this class is to be passed through ArgMiddleware directives.
 * They may get information on the field or modify it to influence the
 * resulting executable schema.
 */
class ArgumentValue
{
    /**
     * @var \GraphQL\Language\AST\InputValueDefinitionNode
     */
    protected $astNode;

    /**
     * @var \Nuwave\Lighthouse\Schema\Values\FieldValue|null
     */
    protected $parentField;

    /**
     * @var \GraphQL\Type\Definition\Type
     */
    protected $type;

    /**
     * ArgumentValue constructor.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $astNode
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $parentField
     * @return void
     */
    public function __construct(InputValueDefinitionNode $astNode, ?FieldValue $parentField = null)
    {
        $this->astNode = $astNode;
        $this->parentField = $parentField;
    }

    /**
     * @return \GraphQL\Language\AST\InputValueDefinitionNode
     */
    public function getAstNode(): InputValueDefinitionNode
    {
        return $this->astNode;
    }

    /**
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue|null
     */
    public function getParentField(): ?FieldValue
    {
        return $this->parentField;
    }

    /**
     * @return \GraphQL\Type\Definition\Type
     */
    public function getType(): Type
    {
        if (! $this->type) {
            $this->type = app(DefinitionNodeConverter::class)->toType($this->astNode->type);
        }

        return $this->type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return  $this->astNode->name->value;
    }
}
