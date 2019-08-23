<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions;
use Nuwave\Lighthouse\Schema\Conversion\DefinitionNodeConverter;

class ArgumentFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * ArgumentFactory constructor.
     *
     * @param  \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory  $directiveFactory
     * @return void
     */
    public function __construct(DirectiveFactory $directiveFactory)
    {
        $this->directiveFactory = $directiveFactory;
    }

    /**
     * Convert input value definitions to a executable types.
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode[]|\GraphQL\Language\AST\NodeList  $definitions
     * @return mixed[]
     */
    public function toTypeMap($definitionNodes): array
    {
        $arguments = [];

        /* @var InputValueDefinitionNode $inputValueDefinitionNode */
        foreach ($definitionNodes as $inputDefinition) {
            $arguments[$inputDefinition->name->value] = $this->convert($inputDefinition);
        }

        return $arguments;
    }

    /**
     * Convert an argument definition to an executable type.
     *
     * The returned array will be used to construct one of:
     * @see \GraphQL\Type\Definition\FieldArgument
     * @see \GraphQL\Type\Definition\InputObjectField
     *
     * @param  \GraphQL\Language\AST\InputValueDefinitionNode  $definitionNode
     * @return mixed[]
     */
    public function convert(InputValueDefinitionNode $definitionNode): array
    {
        $definitionNodeConverter = app(DefinitionNodeConverter::class);
        $type = $definitionNodeConverter->toType($definitionNode->type);

        $config = [
            'name' => $definitionNode->name->value,
            'description' => data_get($definitionNode->description, 'value'),
            'type' => $type,
            'astNode' => $definitionNode,
        ];

        if ($defaultValue = $definitionNode->defaultValue) {
            $config += [
                'defaultValue' => ASTHelper::defaultValueForArgument($defaultValue, $type),
            ];
        }

        $extensions = new ArgumentExtensions();
        $extensions->resolver = $this->directiveFactory
            ->createAssociatedDirectivesOfType($definitionNode, Resolver::class);
        $config['lighthouse'] = $extensions;

        return $config;
    }
}
