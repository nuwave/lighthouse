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
    /** @var InputValueDefinitionNode */
    protected $astNode;

    /** @var FieldValue|null */
    protected $parentField;

    /** @var Type */
    protected $type;

    /**
     * ArgumentValue constructor.
     *
     * @param InputValueDefinitionNode $astNode
     * @param FieldValue               $parentField
     */
    public function __construct(InputValueDefinitionNode $astNode, FieldValue $parentField = null)
    {
        $this->astNode = $astNode;
        $this->parentField = $parentField;
    }

    /**
     * @return InputValueDefinitionNode
     */
    public function getAstNode(): InputValueDefinitionNode
    {
        return $this->astNode;
    }

    /**
     * @return FieldValue|null
     */
    public function getParentField()
    {
        return $this->parentField;
    }

    /**
     * @return Type
     */
    public function getType(): Type
    {
        if (! $this->type) {
            $this->type = resolve(DefinitionNodeConverter::class)->toType($this->astNode->type);
        }

        return $this->type;
    }
}
