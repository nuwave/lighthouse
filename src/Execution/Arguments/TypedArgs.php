<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;

class TypedArgs
{
    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentTypeNodeConverter
     */
    protected $argumentTypeNodeConverter;

    /**
     * @var \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    protected $documentAST;

    /**
     * TypedArgs constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\ASTBuilder  $astBuilder
     * @return void
     */
    public function __construct(ASTBuilder $astBuilder)
    {
        $this->documentAST = $astBuilder->documentAST();
        $this->argumentTypeNodeConverter = new ArgumentTypeNodeConverter($this->documentAST);
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
     * @param  \GraphQL\Language\AST\NodeList|\GraphQL\Language\AST\InputValueDefinitionNode[] $argumentDefinitions
     * @return \Nuwave\Lighthouse\Execution\Arguments\Argument[]
     */
    protected function wrapArgs(array $args, $argumentDefinitions): array
    {
        /** @var \GraphQL\Language\AST\InputValueDefinitionNode[] $argumentDefinitionMap */
        $argumentDefinitionMap = [];
        foreach ($argumentDefinitions as $definition) {
            $argumentDefinitionMap[$definition->name->value] = $definition;
        }

        /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument[] $arguments */
        $arguments = [];

        foreach ($args as $key => $value) {
            /** @var \GraphQL\Language\AST\InputValueDefinitionNode $definition */
            $definition = $argumentDefinitionMap[$key];

            $type = $this->argumentTypeNodeConverter->convert($definition->type);

            $argument = new Argument();
            $argument->directives = $definition->directives;
            $argument->type = $type;

            // We have to do this conversion here and not in the TypeNodeConverter,
            // because the incoming arguments put a bound on recursion depth
            if($type instanceof ListType) {
                $typeInList = $type->type;
                $typeInListName = $typeInList->name;

                $argument->value = [];
                foreach($value as $singleValue) {
                    $argument->value []= $this->wrapWithTypeInfo($singleValue, $typeInListName);
                }
            } else {
                $argument->value = $this->wrapWithTypeInfo($value, $type->name);
            }

            $arguments[$key] = $argument;
        }

        return $arguments;
    }

    /**
     * Wrap a client-given value in type information.
     *
     * @param  mixed  $value
     * @param  string  $typeName
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|mixed
     */
    protected function wrapWithTypeInfo($value, string $typeName)
    {
        // This might be null if the type is
        // - created outside of the schema string
        // - one of the built in types
        $typeDef = $this->documentAST->types[$typeName] ?? null;

        // We recurse down only if the type is an Input
        if($typeDef instanceof InputObjectTypeDefinitionNode) {
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
