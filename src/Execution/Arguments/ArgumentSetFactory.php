<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\DirectiveLocator;

class ArgumentSetFactory
{
    protected DocumentAST $documentAST;

    public function __construct(
        ASTBuilder $astBuilder,
        protected ArgumentTypeNodeConverter $argumentTypeNodeConverter,
        protected DirectiveLocator $directiveLocator,
    ) {
        $this->documentAST = $astBuilder->documentAST();
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  array<mixed>  $args
     */
    public function fromResolveInfo(array $args, ResolveInfo $resolveInfo): ArgumentSet
    {
        $definition = $resolveInfo->fieldDefinition->astNode
            ?? throw new DefinitionException('Can not handle programmatic object types due to missing AST.');

        return $this->wrapArgs($definition, $args);
    }

    /**
     * Wrap client-given args with type information.
     *
     * @param  array<mixed>  $args
     */
    public function wrapArgs(FieldDefinitionNode|InputObjectTypeDefinitionNode $definition, array $args): ArgumentSet
    {
        $argumentSet = new ArgumentSet();
        $argumentSet->directives = $this->directiveLocator->associated($definition);

        $argDefinitions = $definition instanceof FieldDefinitionNode
            ? $definition->arguments
            : $definition->fields;
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
    protected function makeDefinitionMap(iterable $argumentDefinitions): array
    {
        $argumentDefinitionMap = [];

        foreach ($argumentDefinitions as $definition) {
            $argumentDefinitionMap[$definition->name->value] = $definition;
        }

        return $argumentDefinitionMap;
    }

    /** Wrap a single client-given argument with type information. */
    protected function wrapInArgument(mixed $value, InputValueDefinitionNode $definition): Argument
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
     *
     * @return array|mixed|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet
     */
    protected function wrapWithType(mixed $valueOrValues, ListType|NamedType $type)
    {
        // No need to recurse down further if the value is null
        if ($valueOrValues === null) {
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
     * @return ArgumentSet|mixed
     */
    protected function wrapWithNamedType(mixed $value, NamedType $namedType)
    {
        // This might be null if the type is
        // - created outside the schema string
        // - one of the built-in types
        $typeDef = $this->documentAST->types[$namedType->name] ?? null;

        // We recurse down only if the type is an Input
        if ($typeDef instanceof InputObjectTypeDefinitionNode) {
            return $this->wrapArgs($typeDef, $value);
        }

        // Otherwise, we just return the value as is and are done with that subtree
        return $value;
    }
}
