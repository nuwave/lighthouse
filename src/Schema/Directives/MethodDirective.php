<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MethodDirective extends BaseDirective implements FieldResolver, FieldManipulator, DefinedDirective
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Resolve a field by calling a method on the parent object.

Use this if the data is not accessible through simple property access or if you
want to pass argument to the method.
"""
directive @method(
  """
  Specify the method of which to fetch the data from.
  Defaults to the name of the field if not given.
  """
  name: String

  """
  The field arguments to pass (in order) to the underlying method. Each string in the array
  should correspond to an argument of the field.
  """
  pass: [String!]
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) {
                /** @var string $method */
                $method = $this->directiveArgValue(
                    'name',
                    $this->nodeName()
                );

                if($this->directiveArgValue('passOrdered')) {
                    $orderedArgs = [];
                    foreach($this->definitionNode->arguments as $argDefinition) {
                        $orderedArgs []= $args[$argDefinition->name->value] ?? null;
                    }

                    return call_user_func_array([$root, $method], $orderedArgs);
                }

                // Bring the arguments into the correct order in which to pass them
                if($paramsToBind = $this->directiveArgValue('pass')) {
                    $parameters = array_map(
                        function (string $argumentName) use ($args) {
                            // An argument may simply not be passed, so we fall back to null
                            return $args[$argumentName] ?? null;
                        },
                        $paramsToBind
                    );

                    return call_user_func_array([$root, $method], $parameters);
                }

                return call_user_func([$root, $method], $root, $args, $context, $resolveInfo);
            }
        );
    }

    public function manipulateFieldDefinition(
        DocumentAST &$documentAST,
        FieldDefinitionNode &$fieldDefinition,
        ObjectTypeDefinitionNode &$parentType
    ) {
        if ($this->directiveHasArgument('pass')) {
            $paramsToBind = $this->directiveArgValue('pass');

            if (! is_array($paramsToBind)) {
                throw new DefinitionException(
                    self::passMustBeAList($this->nodeName())
                );
            }

            foreach ($paramsToBind as $pass) {
                if (! ASTHelper::fieldHasArgument($fieldDefinition, $pass)) {
                    throw new DefinitionException(
                        self::noArgumentMatchingPass($this->nodeName(), $pass)
                    );
                }
            }
        }
    }

    public static function passMustBeAList(string $fieldName): string
    {
        return "The `pass` argument on field {$fieldName} must be a list";
    }

    public static function noArgumentMatchingPass(string $fieldName, string $pass)
    {
        return "No argument to match the `pass` value {$pass} on field {$fieldName}.";
    }
}
