<?php

namespace Nuwave\Lighthouse\Federation;

use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\WrappingType;
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

            /** @var \Nuwave\Lighthouse\Support\Contracts\Directive $directive */
            foreach ($directives as $directive) {
                if ($directive instanceof KeyDirective) {
                    $this->validateKeySelectionSet($directive->fields(), $type);
                }
            }
        }
    }

    /**
     * @throws \Nuwave\Lighthouse\Exceptions\FederationException
     */
    protected function validateKeySelectionSet(SelectionSetNode $selectionSet, ObjectType $type): void
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

            if (
                ASTHelper::hasDirective($type->astNode, ExtendsDirective::NAME)
                && ! ASTHelper::hasDirective($field->astNode, ExternalDirective::NAME)
            ) {
                throw new FederationException("A @key directive on `{$type->name}` specifies the `{$field->name}` field which has no @external directive.");
            }

            $nestedSelection = $selection->selectionSet;
            if (null !== $nestedSelection) {
                $type = $field->getType();
                if ($type instanceof WrappingType) {
                    $type = $type->getWrappedType(true);
                }

                $this->validateKeySelectionSet($nestedSelection, $type);
            }
        }
    }
}
