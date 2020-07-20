<?php

namespace Nuwave\Lighthouse\Validation;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\ProvidesRules;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Traits\HasArgumentValue;

class ValidatorDirective extends BaseDirective implements ArgDirective, ProvidesRules, TypeManipulator, FieldManipulator, DefinedDirective
{
    use HasArgumentValue;

    /**
     * @var \Nuwave\Lighthouse\Validation\Validator|null
     */
    protected $validator;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Provide validation rules through a PHP class.
"""
directive @validator(
  """
  The name of the class to use.

  If defined on an input, this defaults to a class called `{$inputName}Validator` in the
  default validator namespace. For fields, it uses the name of the parent type
  and the field name: `{$parent}{$field}Validator`.
  """
  class: String
) on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION | INPUT_OBJECT
SDL;
    }

    /**
     * @return mixed[]
     * @throws DefinitionException
     */
    public function rules(): array
    {
        return $this->validator()->rules();
    }

    /**
     * @return string[]
     */
    public function messages(): array
    {
        return $this->validator()->messages();
    }

    protected function validator(): Validator
    {
        if (! $this->validator) {
            /** @var \Nuwave\Lighthouse\Validation\Validator $validator */
            $validator = app(
                // We precomputed and validated the full class name at schema build time
                $this->directiveArgValue('class')
            );
            // @phpstan-ignore-next-line Since this directive can only be defined on a field or input, this must be ArgumentSet
            $validator->setArgs($this->argumentValue);

            return $this->validator = $validator;
        }

        return $this->validator;
    }

    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition)
    {
        if (! $typeDefinition instanceof InputObjectTypeDefinitionNode) {
            throw new DefinitionException(
                "Can not use @validator on non input type {$typeDefinition->name->value}."
            );
        }

        if ($this->directiveHasArgument('class')) {
            $classCandidate = $this->directiveArgValue('class');
        } else {
            $classCandidate = $typeDefinition->name->value.'Validator';
        }

        $this->setFullClassnameOnDirective($typeDefinition, $classCandidate);
    }

    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType
    ) {
        if ($this->directiveHasArgument('class')) {
            $classCandidate = $this->directiveArgValue('class');
        } else {
            $classCandidate = $parentType->name->value
                .'\\'
                .ucfirst($fieldDefinition->name->value)
                .'Validator';
        }

        $this->setFullClassnameOnDirective($fieldDefinition, $classCandidate);
    }

    /**
     * Set the full classname of the validator class on the directive.
     *
     * This allows accessing it straight away when resolving the query.
     *
     * @param  \GraphQL\Language\AST\TypeDefinitionNode|\GraphQL\Language\AST\FieldDefinitionNode  $definition
     */
    protected function setFullClassnameOnDirective(Node &$definition, string $classCandidate): void
    {
        $validatorClass = $this->namespaceValidatorClass($classCandidate);

        foreach ($definition->directives as $directive) {
            if ($directive->name->value === $this->name()) {
                $directive->arguments = ASTHelper::mergeUniqueNodeList(
                    $directive->arguments,
                    [PartialParser::argument('class: "'.addslashes($validatorClass).'"')],
                    true
                );
            }
        }
    }

    protected function namespaceValidatorClass(string $classCandidate): string
    {
        return $this->namespaceClassName(
            $classCandidate,
            (array) config('lighthouse.namespaces.validators'),
            function (string $classCandidate): bool {
                return is_subclass_of($classCandidate, Validator::class);
            }
        );
    }
}
