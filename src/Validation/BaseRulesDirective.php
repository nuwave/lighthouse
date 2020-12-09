<?php

namespace Nuwave\Lighthouse\Validation;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\ArgumentValidation;

abstract class BaseRulesDirective extends BaseDirective implements ArgumentValidation, ArgManipulator
{
    public function rules(): array
    {
        $rules = $this->directiveArgValue('apply');

        // Custom rules may be referenced through their fully qualified class name.
        // The Laravel validator expects a class instance to be passed, so we
        // resolve any given rule where a corresponding class exists.
        foreach ($rules as $key => $rule) {
            if (class_exists($rule)) {
                $rules[$key] = app($rule);
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return (array) $this->directiveArgValue('messages');
    }

    public function attribute(): ?string
    {
        return $this->directiveArgValue('attribute');
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ) {
        $rules = $this->directiveArgValue('apply');

        if (! is_array($rules)) {
            throw new DefinitionException("The apply argument of @{$this->name()} on {$this->nodeName()} has to be an array, got: {$rules}");
        }
    }
}
