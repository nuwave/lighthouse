<?php

namespace Nuwave\Lighthouse\Schema\Directives\Args;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;
use Nuwave\Lighthouse\Schema\Values\ArgumentValue;
use Nuwave\Lighthouse\Support\Contracts\ArgMiddleware;
use Nuwave\Lighthouse\Support\Contracts\InputValueManipulator;

class RulesDirective implements ArgMiddleware, InputValueManipulator
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'rules';
    }

    /**
     * @param InputValueDefinitionNode      $inputValue
     * @param InputObjectTypeDefinitionNode $parentType
     * @param DocumentAST                   $current
     * @param DocumentAST                   $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(
        InputValueDefinitionNode $inputValue,
        InputObjectTypeDefinitionNode $parentType,
        DocumentAST $current,
        DocumentAST $original
    ) {
        $directive = collect($inputValue->directives)
            ->first(function (DirectiveNode $directive) {
                return $directive->name->value === $this->name();
            });

        return RuleFactory::build($directive, $inputValue, $parentType, $current);
    }

    /**
     * Apply transformations on the ArgumentValue.
     *
     * @param ArgumentValue $value
     *
     * @return ArgumentValue
     */
    public function handleArgument(ArgumentValue $value)
    {
        return $value;
    }
}
