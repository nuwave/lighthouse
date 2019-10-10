<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;

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
     * TypedArgs constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\ASTBuilder  $astBuilder
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentTypeNodeConverter  $argumentTypeNodeConverter
     * @return void
     */
    public function __construct(ASTBuilder $astBuilder, ArgumentTypeNodeConverter $argumentTypeNodeConverter)
    {
        $this->documentAST = $astBuilder->documentAST();
        $this->argumentTypeNodeConverter = $argumentTypeNodeConverter;
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
        $argumentSet->directives = $fieldDefinition->directives;
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
        $argument->directives = $definition->directives;
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

            $values = [];
            foreach ($valueOrValues as $singleValue) {
                $values [] = $this->wrapWithNamedType($singleValue, $typeInList);
            }

            return $values;
        } else {
            return $this->wrapWithNamedType($valueOrValues, $type);
        }
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
        // This might be null if the type is
        // - created outside of the schema string
        // - one of the built in types
        $typeDef = $this->documentAST->types[$namedType->name] ?? null;

        // We recurse down only if the type is an Input
        if ($typeDef instanceof InputObjectTypeDefinitionNode) {
            $subArgumentSet = new ArgumentSet();
            $subArgumentSet->directives = $typeDef->directives;
            $subArgumentSet->arguments = $this->wrapArgs($value, $typeDef->fields);

            return $subArgumentSet;
        } else {
            // Otherwise, we just return the value as is and are down with that subtree
            return $value;
        }
    }
}
