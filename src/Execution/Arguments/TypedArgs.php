<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class TypedArgs
{
    /**
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentTypeNodeConverter
     */
    protected $argumentTypeNodeConverter;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * TypedArgs constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\ASTBuilder  $astBuilder
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentTypeNodeConverter  $argumentTypeNodeConverter
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @return void
     */
    public function __construct(
        ASTBuilder $astBuilder,
        ArgumentTypeNodeConverter $argumentTypeNodeConverter,
        DirectiveFactory $directiveFactory
    ) {
        $this->documentAST = $astBuilder->documentAST();
        $this->argumentTypeNodeConverter = $argumentTypeNodeConverter;
        $this->directiveFactory = $directiveFactory;
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  array  $args
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    public function fromResolveInfo(array $args, ResolveInfo $resolveInfo): ArgumentSet
    {
        $parentName = $resolveInfo->parentType->name;
        $fieldName = $resolveInfo->fieldName;

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $parentDefinition */
        $parentDefinition = $this->documentAST->types[$parentName];
        /** @var \GraphQL\Language\AST\FieldDefinitionNode $fieldDefinition */
        $fieldDefinition = ASTHelper::firstByName($parentDefinition->fields, $fieldName);

        return $this->fromField($args, $fieldDefinition);
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  array  $args
     * @param  \GraphQL\Language\AST\FieldDefinitionNode  $fieldDefinition
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    public function fromField(array $args, FieldDefinitionNode $fieldDefinition): ArgumentSet
    {
        $argumentSet = new ArgumentSet();
        $argumentSet->directives = $this->directiveFactory->createAssociatedDirectives($fieldDefinition);
        $argumentSet->arguments = $this->wrapArgs($args, $fieldDefinition->arguments);

        return $argumentSet;
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  mixed[]  $args
     * @param  \GraphQL\Language\AST\NodeList|\GraphQL\Language\AST\InputValueDefinitionNode[]  $argumentDefinitions
     * @return \Nuwave\Lighthouse\Execution\Arguments\Argument[]
     */
    protected function wrapArgs(array $args, $argumentDefinitions): array
    {
        $argumentDefinitionMap = $this->makeDefinitionMap($argumentDefinitions);

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument[] $arguments */
        $arguments = [];

        foreach ($args as $key => $value) {
            /** @var \GraphQL\Language\AST\InputValueDefinitionNode $definition */
            $definition = $argumentDefinitionMap[$key];

            $arguments[$key] = $this->wrapInArgument($value, $definition);
        }

        return $arguments;
    }

    /**
     * Make a map with the name as keys.
     *
     * @param  \GraphQL\Language\AST\NodeList|\GraphQL\Language\AST\InputValueDefinitionNode[]  $argumentDefinitions
     * @return \GraphQL\Language\AST\NodeList|\GraphQL\Language\AST\InputValueDefinitionNode[]
     */
    protected function makeDefinitionMap($argumentDefinitions): array
    {
        /** @var \GraphQL\Language\AST\InputValueDefinitionNode[] $argumentDefinitionMap */
        $argumentDefinitionMap = [];

        foreach ($argumentDefinitions as $definition) {
            $argumentDefinitionMap[$definition->name->value] = $definition;
        }

        return $argumentDefinitionMap;
    }

    /**
     * Wrap a single client-given argument with type information.
     *
     * @param  mixed  $value
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $definition
     * @return \Nuwave\Lighthouse\Execution\Arguments\Argument
     */
    protected function wrapInArgument($value, InputValueDefinitionNode $definition): Argument
    {
        $type = $this->argumentTypeNodeConverter->convert($definition->type);

        $argument = new Argument();
        $argument->directives = $this->directiveFactory->createAssociatedDirectives($definition);
        $argument->type = $type;
        $argument->value = $this->wrapWithType($value, $type);

        return $argument;
    }

    /**
     * Wrap a client-given value with information from a type.
     *
     * @param  mixed|mixed[]  $valueOrValues
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType  $type
     * @return array|mixed|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected function wrapWithType($valueOrValues, $type)
    {
        // We have to do this conversion here and not in the TypeNodeConverter,
        // because the incoming arguments put a bound on recursion depth
        if ($type instanceof ListType) {
            $typeInList = $type->type;

            if (is_array($valueOrValues)) {
                $values = [];
                foreach ($valueOrValues as $singleValue) {
                    $values [] = $this->wrapWithNamedType($singleValue, $typeInList);
                }

                return $values;
            }

            // This case happens if `null` is passed
            return $this->wrapWithNamedType($valueOrValues, $typeInList);
        }

        return $this->wrapWithNamedType($valueOrValues, $type);
    }

    /**
     * Wrap a client-given value with information from a named type.
     *
     * @param  mixed  $value
     * @param  \Nuwave\Lighthouse\Execution\Arguments\NamedType  $namedType
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|mixed
     */
    protected function wrapWithNamedType($value, NamedType $namedType)
    {
        // As GraphQL does not allow empty input objects, we return null as is
        if ($value === null) {
            return;
        }

        // This might be null if the type is
        // - created outside of the schema string
        // - one of the built in types
        $typeDef = $this->documentAST->types[$namedType->name] ?? null;

        // We recurse down only if the type is an Input
        if ($typeDef instanceof InputObjectTypeDefinitionNode) {
            $subArgumentSet = new ArgumentSet();
            $subArgumentSet->directives = $this->directiveFactory->createAssociatedDirectives($typeDef);
            $subArgumentSet->arguments = $this->wrapArgs($value, $typeDef->fields);

            return $subArgumentSet;
        }

        // Otherwise, we just return the value as is and are down with that subtree
        return $value;
    }
}
