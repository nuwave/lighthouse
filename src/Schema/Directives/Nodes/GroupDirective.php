<?php

namespace Nuwave\Lighthouse\Schema\Directives\Nodes;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use Nuwave\Lighthouse\Schema\Utils\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\NodeValue;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\SchemaManipulator;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class GroupDirective implements SchemaManipulator
{
    use HandlesDirectives;

    /**
     * Name of the directive.
     *
     * @return string
     */
    public static function name()
    {
        return 'group';
    }

    /**
     * @param Node $definitionNode
     * @param DocumentAST $current
     * @param DocumentAST $original
     * @param ObjectTypeDefinitionNode|null $parentType
     *
     * @return DocumentAST
     * @throws DirectiveException
     */
    public function manipulateSchema(Node $definitionNode, DocumentAST $current, DocumentAST $original, ObjectTypeDefinitionNode $parentType = null)
    {
        $nodeName = $definitionNode->name->value;

        if (! in_array($nodeName, ['Query', 'Mutation'])) {
            $message = "The group directive can only be placed on a Query or Mutation [$nodeName]";

            throw new DirectiveException($message);
        }

        $definitionNode = $this->setMiddlewareDirectiveOnFields($definitionNode);
        $definitionNode = $this->setNamespaceDirectiveOnFields($definitionNode);

        $current->setObjectType($definitionNode);

        return $current;
    }

    /**
     * @param $definitionNode
     * @return mixed
     *
     * @throws \Exception
     */
    protected function setMiddlewareDirectiveOnFields($definitionNode)
    {
        $middlewareValues = $this->directiveArgValue(
            $this->nodeDirective($definitionNode, self::name()),
            'middleware'
        );

        if(! $middlewareValues){
            return $definitionNode;
        }

        $middlewareValues = '["' . implode('", "', $middlewareValues) . '"]';
        $middlewareDirective = DocumentAST::parseDirectives("@middleware(checks: $middlewareValues)");

        $definitionNode->fields = new NodeList(collect($definitionNode->fields)->map(function(FieldDefinitionNode $fieldDefinition) use ($middlewareDirective){
            $fieldDefinition->directives = $fieldDefinition->directives->merge($middlewareDirective);
            return $fieldDefinition;
        })->toArray());

        return $definitionNode;
    }

    /**
     * @param $definitionNode
     * @return mixed
     * @throws DirectiveException
     */
    protected function setNamespaceDirectiveOnFields($definitionNode)
    {
        $namespaceValue = $this->directiveArgValue(
            $this->nodeDirective($definitionNode, self::name()),
            'namespace'
        );

        if(!$namespaceValue){
            return $definitionNode;
        }

        if(! is_string($namespaceValue)){
            throw new DirectiveException('The value of the namespace directive on has to be a string');
        }

        $namespaceDirective = DocumentAST::parseDirectives('@namespace(value: "' . addslashes($namespaceValue) . '")');

        $definitionNode->fields = new NodeList(collect($definitionNode->fields)->map(function(FieldDefinitionNode $fieldDefinition) use ($namespaceDirective){
            $fieldDefinition->directives = $fieldDefinition->directives->merge($namespaceDirective);
            return $fieldDefinition;
        })->toArray());

        return $definitionNode;
    }
}
