<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ResolveInfo;
use InvalidArgumentException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\DirectiveLocator;

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
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    public function __construct(
        ASTBuilder $astBuilder,
        ArgumentTypeNodeConverter $argumentTypeNodeConverter,
        DirectiveLocator $directiveLocator
    ) {
        $this->documentAST = $astBuilder->documentAST();
        $this->argumentTypeNodeConverter = $argumentTypeNodeConverter;
        $this->directiveLocator = $directiveLocator;
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  array<mixed>  $args
     */
    public function fromResolveInfo(array $args, ResolveInfo $resolveInfo): ArgumentSet
    {
        /**
         * TODO handle programmatic types without an AST gracefully.
         *
         * @var \GraphQL\Language\AST\FieldDefinitionNode $definition
         */
        $definition = $resolveInfo->fieldDefinition->astNode;

        return $this->wrapArgs($definition, $args);
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  \GraphQL\Language\AST\FieldDefinitionNode|\GraphQL\Language\AST\InputObjectTypeDefinitionNode  $definition
     * @param  array<mixed>  $args
     */
    public function wrapArgs(Node $definition, array $args): ArgumentSet
    {
        $argumentSet = new ArgumentSet();
        $argumentSet->directives = $this->directiveLocator->associated($definition);

        if ($definition instanceof FieldDefinitionNode) {
            $argDefinitions = $definition->arguments;
        } elseif ($definition instanceof InputObjectTypeDefinitionNode) {
            $argDefinitions = $definition->fields;
        } else {
            throw new InvalidArgumentException('Got unexpected node of type ' . get_class($definition));
        }

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
     *
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
     * @param  mixed  $value  the client given value
     */
    protected function wrapInArgument($value, InputValueDefinitionNode $definition): Argument
    {
        $type = $this->argumentTypeNodeConverter->convert($definition->type);

        $argument = new Argument();
        $argument->directives = $this->directiveLocator->associated($definition);
        $argument->type = $type;
        $argument->value = $this->wrapWithType($value, $type);

        return $argument;
    }

    /**
     * Wrap a client-given value with information from a type.
     *
     * @param  mixed|array<mixed>  $valueOrValues
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ListType|\Nuwave\Lighthouse\Execution\Arguments\NamedType  $type
     *
     * @return array|mixed|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected function wrapWithType($valueOrValues, $type)
    {
        // No need to recurse down further if the value is null
        if (null === $valueOrValues) {
            return null;
        }

        // We have to do this conversion as we are resolving a client query
        // because the incoming arguments put a bound on recursion depth
        if ($type instanceof ListType) {
            $typeInList = $type->type;

            $values = [];
            foreach ($valueOrValues as $singleValue) {
                $values[] = $this->wrapWithType($singleValue, $typeInList);
            }

            return $values;
        }

        return $this->wrapWithNamedType($valueOrValues, $type);
    }

    /**
     * Wrap a client-given value with information from a named type.
     *
     * @param  mixed  $value  the client given value
     *
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
