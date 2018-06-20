<?php

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Factories\NodeFactory;
use Nuwave\Lighthouse\Schema\Resolvers\NodeResolver;
use Nuwave\Lighthouse\Schema\Values\NodeValue;

class SchemaBuilder
{
    /**
     * Definition weights.
     *
     * @var array
     */
    protected $weights = [
        \GraphQL\Language\AST\ScalarTypeDefinitionNode::class => 0,
        \GraphQL\Language\AST\InterfaceTypeDefinitionNode::class => 1,
        \GraphQL\Language\AST\UnionTypeDefinitionNode::class => 2,
    ];

    /**
     * Build an executable schema from AST.
     *
     * @param DocumentAST $documentAST
     *
     * @return Schema
     */
    public function build($documentAST)
    {
        $types = $this->convertTypes($documentAST);
        $this->loadRootOperationFields($types);

        $config = SchemaConfig::create()
            // Always set Query since it is required
            ->setQuery($types->firstWhere('name', 'Query'))
            ->setTypes($types->reject($this->isOperationType())->toArray())
            ->setDirectives($this->convertDirectives($documentAST)->toArray())
            ->setTypeLoader(function ($name) {
                return graphql()->types()->get($name);
            });

        // Those are optional so only add them if they are present in the schema
        if ($mutation = $types->firstWhere('name', 'Mutation')) {
            $config->setMutation($mutation);
        }
        if ($subscription = $types->firstWhere('name', 'Subscription')) {
            $config->setSubscription($subscription);
        }

        return new Schema($config);
    }

    /**
     * The fields for the root operations have to be loaded in advance.
     *
     * This is because they might have to register middleware.
     * Other fields can be lazy-loaded to improve performance.
     *
     * @param Collection $types
     */
    protected function loadRootOperationFields(Collection $types)
    {
        $types->filter($this->isOperationType())
            ->each(function (ObjectType $type) {
                // This resolves the fields which causes the fields MiddlewareDirective to run
                // and thus register the (Laravel)-Middleware for the fields.
                $type->getFields();
            });
    }

    /**
     * Callback to determine whether a type is one of the three root operation types.
     *
     * @return \Closure
     */
    protected function isOperationType()
    {
        return function (Type $type) {
            return in_array($type->name, ['Query', 'Mutation', 'Subscription']);
        };
    }

    /**
     * Convert definitions to types.
     *
     * @param DocumentAST $document
     *
     * @return Collection
     */
    public function convertTypes(DocumentAST $document)
    {
        return $document->typeDefinitions()
            ->sortBy(function (TypeDefinitionNode $typeDefinition) {
                return array_get($this->weights, get_class($typeDefinition), 9);
            })->map(function (TypeDefinitionNode $typeDefinition) {
                return (new NodeFactory())->handle(new NodeValue($typeDefinition));
            })->each(function (Type $type) {
                // Register in global type registry
                graphql()->types()->register($type);
            });
    }

    /**
     * Set custom client directives.
     *
     * @param DocumentAST $document
     *
     * @return Collection
     */
    protected function convertDirectives(DocumentAST $document)
    {
        return $document->directives()->map(function (DirectiveDefinitionNode $directive) {
            return new Directive([
                'name' => $directive->name->value,
                'locations' => collect($directive->locations)->map(function ($location) {
                    return $location->value;
                })->toArray(),
                'args' => collect($directive->arguments)->map(function (InputValueDefinitionNode $argument) {
                    return new FieldArgument([
                        'name' => $argument->name->value,
                        'defaultValue' => data_get($argument, 'defaultValue.value', null),
                        'description' => $argument->description,
                        'type' => NodeResolver::resolve($argument->type),
                    ]);
                })->toArray(),
                'astNode' => $directive,
            ]);
        });
    }
}
