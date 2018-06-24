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
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class RulesDirective implements ArgMiddleware, InputValueManipulator
{
    // TODO: Remove this need for this
    use HandlesDirectives;

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
        // NOTE: We currently cannot just get the current directive off of
        // the value because the 'rules' get reset with each new ArgumentValue.
        $rules = collect($value->getArg()->directives)
            ->filter(function (DirectiveNode  $directive) {
                return $directive->name->value === $this->name();
            })->mapWithKeys(function (DirectiveNode $directive) {
                $path = $this->directiveArgValue($directive, 'path');
                $rules = $this->directiveArgValue($directive, 'apply', []);

                if (empty($path) || empty($rules)) {
                    return null;
                }

                return [$path => $rules];
            })->filter()->toArray();

        $current = $value->getValue();
        $current['validation'] = array_merge(array_get($current, 'validation', []), $rules);

        return $value->setValue($current);
    }
}
