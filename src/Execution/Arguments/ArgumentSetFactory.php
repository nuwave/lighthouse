<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ResolveInfo;
use InvalidArgumentException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;

class ArgumentSetFactory
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
     * @param  array<mixed>  $args
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    public function fromResolveInfo(array $args, ResolveInfo $resolveInfo): ArgumentSet
    {
        $parentName = $resolveInfo->parentType->name;
        $fieldName = $resolveInfo->fieldName;

        /** @var \GraphQL\Language\AST\ObjectTypeDefinitionNode $parentDefinition */
        $parentDefinition = $this->documentAST->types[$parentName];

        /** @var \GraphQL\Language\AST\FieldDefinitionNode $fieldDefinition */
        // @phpstan-ignore-next-line graphql-php types are unnecessarily nullable
        $fieldDefinition = ASTHelper::firstByName($parentDefinition->fields, $fieldName);

        return $this->wrapArgs($fieldDefinition, $args);
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode|\GraphQL\Language\AST\InputObjectTypeDefinitionNode  $definition
     * @param  mixed[]  $args
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    public function wrapArgs(Node $definition, array $args): ArgumentSet
    {
        $argumentSet = new ArgumentSet();
        $argumentSet->directives = $this->directiveFactory->createAssociatedDirectives($definition);

        if ($definition instanceof FieldDefinitionNode) {
            $argDefinitions = $definition->arguments;
        } elseif ($definition instanceof InputObjectTypeDefinitionNode) {
            $argDefinitions = $definition->fields;
        } else {
            throw new InvalidArgumentException('Got unexpected node of type '.get_class($definition));
        }

        // @phpstan-ignore-next-line graphql-php types are unnecessarily nullable
        $argumentDefinitionMap = $this->makeDefinitionMap($argDefinitions);

        foreach ($argumentDefinitionMap as $name => $definition) {
            if (array_key_exists($name, $args)) {
                $argumentSet->arguments[$name] = $this->wrapInArgument($args[$name], $definition);
            } else {
                $argumentSet->undefined[$name] = $this->wrapInArgument(null, $definition);
            }
        }

        return $argumentSet;
    }

    /**
     * Make a map with the name as keys.
     *
     * @param  iterable<\GraphQL\Language\AST\InputValueDefinitionNode>  $argumentDefinitions
     * @return array<string, \GraphQL\Language\AST\InputValueDefinitionNode>
     */
    protected function makeDefinitionMap($argumentDefinitions): array
    {
        $argumentDefinitionMap = [];

        foreach ($argumentDefinitions as $definition) {
            $argumentDefinitionMap[$definition->name->value] = $definition;
        }

        return $argumentDefinitionMap;
    }

    /**
     * Wrap a single client-given argument with type information.
     *
     * @param  mixed  $value The client given value.
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
        // No need to recurse down further if the value is null
        if ($valueOrValues === null) {
            return;
        }

        // We have to do this conversion as we are resolving a client query
        // because the incoming arguments put a bound on recursion depth
        if ($type instanceof ListType) {
            $typeInList = $type->type;

            $values = [];
            foreach ($valueOrValues as $singleValue) {
                $values [] = $this->wrapWithType($singleValue, $typeInList);
            }

            return $values;
        }

        return $this->wrapWithNamedType($valueOrValues, $type);
    }

    /**
     * Wrap a client-given value with information from a named type.
     *
     * @param  mixed  $value The client given value.
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
            return $this->wrapArgs($typeDef, $value);
        }

        // Otherwise, we just return the value as is and are done with that subtree
        return $value;
    }
}
