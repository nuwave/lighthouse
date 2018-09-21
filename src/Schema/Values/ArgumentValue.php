<?php

namespace Nuwave\Lighthouse\Schema\Values;

use GraphQL\Type\Definition\Type;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class ArgumentValue
{
    /** @var InputValueDefinitionNode */
    protected $astNode;
    
    /** @var FieldValue */
    protected $parentField;

    /** @var Type */
    protected $type;
    
    /** @var \Closure[] */
    protected $transformers = [];
    
    /**
     * ArgumentValue constructor.
     *
     * @param FieldValue $parentField
     * @param InputValueDefinitionNode $astNode
     */
    public function __construct(FieldValue $parentField, InputValueDefinitionNode $astNode)
    {
        $this->parentField = $parentField;
        $this->astNode = $astNode;
    }
    
    /**
     * @return InputValueDefinitionNode
     */
    public function getAstNode(): InputValueDefinitionNode
    {
        return $this->astNode;
    }
    
    /**
     * @return FieldValue
     */
    public function getParentField(): FieldValue
    {
        return $this->parentField;
    }
    
    /**
     * @return Type
     */
    public function getType(): Type
    {
        if(!$this->type){
            $this->type = resolve(DefinitionNodeConverter::class)->toType($this->astNode->type);
        }
        
        return $this->type;
    }
    
    /**
     * @return \Closure[]
     */
    public function getTransformers(): array
    {
        return $this->transformers;
    }
    
    /**
     * @param \Closure $transformer
     *
     * @return ArgumentValue
     */
    public function addTransformer(\Closure $transformer): ArgumentValue
    {
        $this->transformers[] = $transformer;
        
        return $this;
    }
}
