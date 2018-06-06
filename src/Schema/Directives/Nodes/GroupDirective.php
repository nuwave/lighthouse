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
use Nuwave\Lighthouse\Support\Contracts\SchemaGenerator;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Traits\HandlesDirectives;

class GroupDirective implements NodeMiddleware, SchemaGenerator
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
     * Handle node value.
     *
     * @param NodeValue $value
     *
     * @return NodeValue
     */
    public function handleNode(NodeValue $value)
    {
        $this->setNamespace($value);
        $this->setMiddleware($value);

        return $value;
    }

    /**
     * Set namespace on node.
     *
     * @param NodeValue $value [description]
     */
    protected function setNamespace(NodeValue $value)
    {
        $namespace = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'namespace'
        );

        if ($namespace) {
            $value->setNamespace($namespace);
        }
    }

    /**
     * Set middleware for field.
     *
     * @param NodeValue $value
     */
    protected function setMiddleware(NodeValue $value)
    {
        $node = $value->getNodeName();

        if (! in_array($node, ['Query', 'Mutation'])) {
            $message = 'Middleware can only be placed on a Query or Mutation ['.$node.']';

            throw new DirectiveException($message);
        }

        $middleware = $this->directiveArgValue(
            $this->nodeDirective($value->getNode(), self::name()),
            'middleware'
        );

        $container = graphql()->middleware();
        $middleware = is_string($middleware) ? [$middleware] : $middleware;

        if (empty($middleware)) {
            return;
        }

        foreach ($value->getNodeFields() as $field) {
            'Query' == $node
                ? $container->registerQuery($field->name->value, $middleware)
                : $container->registerMutation($field->name->value, $middleware);
        }
    }

    /**
     * @param Node $definitionNode
     * @param DocumentAST $current
     * @param DocumentAST $original
     * @param ObjectTypeDefinitionNode|null $parentType
     *
     * @return DocumentAST
     */
    public function handleSchemaGeneration(Node $definitionNode, DocumentAST $current, DocumentAST $original, ObjectTypeDefinitionNode $parentType = null)
    {
        $nodeName = $definitionNode->name->value;

        if (! in_array($nodeName, ['Query', 'Mutation'])) {
            $message = "The group directive can only be placed on a Query or Mutation [$nodeName]";

            throw new DirectiveException($message);
        }

        dd($definitionNode->directives);

        $middlewareDirectives = $this->middlewareFieldDirective();

        dd($definitionNode->fields);
        collect($definitionNode->fields)->transform(function(FieldDefinitionNode $fieldDefinition) use ($middlewareDirectives){
//            $fieldDefinition->directives->merge()
        });

    }
//
//    /**
//     * @return NodeList
//     */
//    protected function middlewareFieldDirective()
//    {
//        $middleware = $this->directiveArgValue(
//            $this->nodeDirective($definitionNode, self::name()),
//            'middleware'
//        );
////        dd(implode)
//
//        $middlewareDirectives = DocumentAST::parseDirectives("@middleware(checks: ")
//    }
}
