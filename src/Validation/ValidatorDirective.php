<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Validation;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\Parser;
use Illuminate\Container\Container;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgumentSetValidation;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Traits\HasArgumentValue;

class ValidatorDirective extends BaseDirective implements ArgDirective, ArgumentSetValidation, TypeManipulator, FieldManipulator
{
    use HasArgumentValue;

    protected ?Validator $validator = null;

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Provide validation rules through a PHP class.
"""
directive @validator(
  """
  The name of the class to use.

  If defined on an input, this defaults to a class called `{$inputName}Validator` in the
  default validator namespace. For fields, it uses the namespace of the parent type
  and the field name: `{$parent}\{$field}Validator`.
  """
  class: String
) repeatable on ARGUMENT_DEFINITION | INPUT_FIELD_DEFINITION | FIELD_DEFINITION | INPUT_OBJECT
GRAPHQL;
    }

    public function rules(): array
    {
        return $this->validator()->rules();
    }

    public function messages(): array
    {
        return $this->validator()->messages();
    }

    public function attributes(): array
    {
        return $this->validator()->attributes();
    }

    protected function validator(): Validator
    {
        if (! isset($this->validator)) {
            // We precomputed and validated the full class name at schema build time
            $validatorClass = $this->directiveArgValue('class');

            $validator = Container::getInstance()->make($validatorClass);
            assert($this->argumentValue instanceof ArgumentSet, 'this directive can only be defined on a field or input');
            $validator->setArgs($this->argumentValue);

            return $this->validator = $validator;
        }

        return $this->validator;
    }

    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        if (! $typeDefinition instanceof InputObjectTypeDefinitionNode) {
            throw new DefinitionException("Can not use @validator on non input type {$typeDefinition->getName()->value}.");
        }

        $this->setFullClassnameOnDirective(
            $typeDefinition,
            $this->directiveArgValue('class', "{$typeDefinition->name->value}Validator"),
        );
    }

    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $parentName = $parentType->name->value;
        $upperFieldName = ucfirst($fieldDefinition->name->value);

        $this->setFullClassnameOnDirective(
            $fieldDefinition,
            $this->directiveArgValue('class', "{$parentName}\\{$upperFieldName}Validator"),
        );
    }

    /**
     * Set the full classname of the validator class on the directive.
     *
     * This allows accessing it straight away when resolving the query.
     *
     * @param  (\GraphQL\Language\AST\TypeDefinitionNode&\GraphQL\Language\AST\Node)|\GraphQL\Language\AST\FieldDefinitionNode  $definition
     */
    protected function setFullClassnameOnDirective(Node &$definition, string $classCandidate): void
    {
        $validatorClass = $this->namespaceValidatorClass($classCandidate);

        // @phpstan-ignore-next-line The passed in Node types all have the property $directives
        foreach ($definition->directives as $directive) {
            if ($directive->name->value === $this->name()) {
                $validatorClassEscaped = addslashes($validatorClass);
                $directive->arguments = ASTHelper::mergeUniqueNodeList(
                    $directive->arguments,
                    [Parser::argument(/** @lang GraphQL */ <<<GRAPHQL
                        class: "{$validatorClassEscaped}"
                    GRAPHQL)],
                    true,
                );
            }
        }
    }

    /** @return class-string<\Nuwave\Lighthouse\Validation\Validator> */
    protected function namespaceValidatorClass(string $classCandidate): string
    {
        $validatorClassName = $this->namespaceClassName(
            $classCandidate,
            (array) config('lighthouse.namespaces.validators'),
            static fn (string $classCandidate): bool => is_subclass_of($classCandidate, Validator::class),
        );
        assert(is_subclass_of($validatorClassName, Validator::class));

        return $validatorClassName;
    }
}
