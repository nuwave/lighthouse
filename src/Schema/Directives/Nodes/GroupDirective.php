<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Directives\Fields\NamespaceDirective;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

/**
 * Class GroupDirective.
 *
 * This directive is kept for compatibility reasons but is superseded by
 * NamespaceDirective and MiddlewareDirective.
 *
 * @deprecated Will be removed in next major version
 */
class GroupDirective extends BaseDirective implements NodeManipulator
{
    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'group';
    }

    /**
     * @param Node $node
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @throws DirectiveException
     *
     * @return DocumentAST
     * @throws \Nuwave\Lighthouse\Support\Exceptions\DocumentASTException
     */
    public function manipulateSchema(Node $node, DocumentAST $current, DocumentAST $original): DocumentAST
    {
        $nodeName = $node->name->value;

        if (!in_array($nodeName, ['Query', 'Mutation'])) {
            $message = "The group directive can only be placed on a Query or Mutation [$nodeName]";

            throw new DirectiveException($message);
        }

        $node = $this->setMiddlewareDirectiveOnFields($node);
        $node = $this->setNamespaceDirectiveOnFields($node);

        $current->setDefinition($node);

        return $current;
    }

    /**
     * @param ObjectTypeDefinitionNode $objectType
     *
     * @throws \Exception
     *
     * @return ObjectTypeDefinitionNode
     */
    protected function setMiddlewareDirectiveOnFields(ObjectTypeDefinitionNode $objectType): ObjectTypeDefinitionNode
    {
        $middlewareValues = $this->directiveArgValue('middleware');

        if (!$middlewareValues) {
            return $objectType;
        }

        $middlewareValues = '["' . implode('", "', $middlewareValues) . '"]';
        $middlewareDirective = PartialParser::directive("@middleware(checks: $middlewareValues)");

        $objectType->fields = new NodeList(collect($objectType->fields)->map(function (FieldDefinitionNode $fieldDefinition) use ($middlewareDirective) {
            $fieldDefinition->directives = $fieldDefinition->directives->merge([$middlewareDirective]);

            return $fieldDefinition;
        })->toArray());

        return $objectType;
    }

    /**
     * @param ObjectTypeDefinitionNode $objectType
     *
     * @throws \Exception
     *
     * @return ObjectTypeDefinitionNode
     */
    protected function setNamespaceDirectiveOnFields(ObjectTypeDefinitionNode $objectType): ObjectTypeDefinitionNode
    {
        $namespaceValue = $this->directiveArgValue('namespace');

        if (!$namespaceValue) {
            return $objectType;
        }

        if (!is_string($namespaceValue)) {
            throw new DirectiveException('The value of the namespace directive on has to be a string');
        }

        $namespaceValue = addslashes($namespaceValue);

        $objectType->fields = new NodeList(collect($objectType->fields)->map(function (FieldDefinitionNode $fieldDefinition) use ($namespaceValue) {
            $previousNamespaces = $this->directiveDefinition((new NamespaceDirective())->name(), $fieldDefinition);

            $previousNamespaces = $previousNamespaces
                ? $this->mergeNamespaceOnExistingDirective($namespaceValue, $previousNamespaces)
                : PartialParser::directive("@namespace(field: \"$namespaceValue\", complexity: \"$namespaceValue\")");
            $fieldDefinition->directives = $fieldDefinition->directives->merge([$previousNamespaces]);

            return $fieldDefinition;
        })->toArray());

        return $objectType;
    }

    /**
     * @param string $namespaceValue
     * @param DirectiveNode $directive
     *
     * @return DirectiveNode
     */
    protected function mergeNamespaceOnExistingDirective($namespaceValue, DirectiveNode $directive): DirectiveNode
    {
        $namespaces = PartialParser::arguments([
            "field: \"$namespaceValue\"",
            "complexity: \"$namespaceValue\"",
        ]);

        $directive->arguments = $directive->arguments->merge($namespaces);

        return $directive;
    }
}
