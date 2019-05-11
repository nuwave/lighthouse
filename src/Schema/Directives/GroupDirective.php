<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Collection;
use GraphQL\Language\AST\DirectiveNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;

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
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $documentAST): DocumentAST
    {
        if ($middlewareValues = $this->directiveArgValue('middleware')) {
            $node = MiddlewareDirective::addMiddlewareDirectiveToFields($node, $middlewareValues);
        }

        $node = $this->setNamespaceDirectiveOnFields($node);

        return $documentAST->setDefinition($node);
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode  $objectType
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function setNamespaceDirectiveOnFields($objectType)
    {
        $namespaceValue = $this->directiveArgValue('namespace');

        if (! $namespaceValue) {
            return $objectType;
        }

        if (! is_string($namespaceValue)) {
            throw new DirectiveException('The value of the namespace directive on has to be a string');
        }

        $namespaceValue = addslashes($namespaceValue);

        $objectType->fields = new NodeList(
            (new Collection($objectType->fields))
                ->map(function (FieldDefinitionNode $fieldDefinition) use ($namespaceValue): FieldDefinitionNode {
                    $existingNamespaces = ASTHelper::directiveDefinition(
                        $fieldDefinition,
                        NamespaceDirective::NAME
                    );

                    $newNamespaceDirective = $existingNamespaces
                        ? $this->mergeNamespaceOnExistingDirective($namespaceValue, $existingNamespaces)
                        : PartialParser::directive("@namespace(field: \"$namespaceValue\", complexity: \"$namespaceValue\")");

                    $fieldDefinition->directives = $fieldDefinition->directives->merge([$newNamespaceDirective]);

                    return $fieldDefinition;
                })
                ->all()
        );

        return $objectType;
    }

    /**
     * @param  string  $namespaceValue
     * @param  \GraphQL\Language\AST\DirectiveNode  $directive
     * @return \GraphQL\Language\AST\DirectiveNode
     */
    protected function mergeNamespaceOnExistingDirective(string $namespaceValue, DirectiveNode $directive): DirectiveNode
    {
        $namespaces = PartialParser::arguments([
            "field: \"$namespaceValue\"",
            "complexity: \"$namespaceValue\"",
        ]);

        $directive->arguments = $directive->arguments->merge($namespaces);

        return $directive;
    }
}
