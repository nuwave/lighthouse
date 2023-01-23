<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use Nuwave\Lighthouse\Events\ValidateSchema;
use Nuwave\Lighthouse\Exceptions\FederationException;
use Nuwave\Lighthouse\Federation\Directives\ExtendsDirective;
use Nuwave\Lighthouse\Federation\Directives\ExternalDirective;
use Nuwave\Lighthouse\Federation\Directives\KeyDirective;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;

class SchemaValidator
{
    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    public function __construct(DirectiveLocator $directiveLocator)
    {
        $this->directiveLocator = $directiveLocator;
    }

    public function handle(ValidateSchema $validateSchema): void
    {
        $schema = $validateSchema->schema;

        foreach ($schema->getTypeMap() as $type) {
            if ($type instanceof ObjectType) {
                $this->validateObjectType($type);
            }
        }
    }

    protected function validateObjectType(ObjectType $type): void
    {
        $ast = $type->astNode;
        if (null !== $ast) {
            $directives = $this->directiveLocator->associated($ast);

            foreach ($directives as $directive) {
                if ($directive instanceof KeyDirective) {
                    $this->validateKeySelectionSet($directive->fields(), $type, $ast);
                }
            }
        }
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\FederationException
     */
    protected function validateKeySelectionSet(SelectionSetNode $selectionSet, ObjectType $type, ObjectTypeDefinitionNode $typeAST): void
    {
        foreach ($selectionSet->selections as $selection) {
            if (! $selection instanceof FieldNode) {
                throw new FederationException("Must only use field selections in the `fields` argument of @key, got: {$selection->kind}.");
            }

            try {
                // Throws if the field is not defined
                $field = $type->getField($selection->name->value);
            } catch (InvariantViolation $i) {
                throw new FederationException($i->getMessage(), $i->getCode(), $i);
            }

            $fieldASTNode = $field->astNode;
            if (null === $fieldASTNode) {
                throw new FederationException("Missing AST node for {$type->name}.{$field->name}.");
            }

            if (
                ASTHelper::hasDirective($typeAST, ExtendsDirective::NAME)
                && ! ASTHelper::hasDirective($fieldASTNode, ExternalDirective::NAME)
            ) {
                throw new FederationException("A @key directive on `{$type->name}` specifies the `{$field->name}` field which has no @external directive.");
            }

            $nestedSelection = $selection->selectionSet;
            if (null !== $nestedSelection) {
                $fieldType = Type::getNamedType($field->getType());
                if (! $fieldType instanceof ObjectType) {
                    $notObjectType = Utils::printSafe($fieldType);
                    throw new FederationException("Expected type of field {$type->name}.{$field->name} with subselection to be object type, got: {$notObjectType}.");
                }

                $fieldTypeASTNode = $fieldType->astNode;
                if (null === $fieldTypeASTNode) {
                    throw new FederationException("Missing AST node for {$fieldType->name}.");
                }

                $this->validateKeySelectionSet($nestedSelection, $fieldType, $fieldTypeASTNode);
            }
        }
    }
}
