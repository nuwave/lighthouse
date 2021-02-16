<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use Illuminate\Pipeline\Pipeline;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;

class FieldFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveFactory;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * @var \Illuminate\Pipeline\Pipeline
     */
    protected $pipeline;

    public function __construct(
        DirectiveLocator $directiveLocator,
        ArgumentFactory $argumentFactory,
        Pipeline $pipeline
    ) {
        $this->directiveFactory = $directiveLocator;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
    }

    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @return array<string, mixed> Configuration array for a \GraphQL\Type\Definition\FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldDefinitionNode = $fieldValue->getField();

        // Directives have the first priority for defining a resolver for a field
        $resolverDirective = $this->directiveFactory->exclusiveOfType($fieldDefinitionNode, FieldResolver::class);
        if ($resolverDirective instanceof FieldResolver) {
            $resolverDirective->resolveField($fieldValue);
        } else {
            $fieldValue->useDefaultResolver();
        }

        $this->pipeline
            ->send($fieldValue)
            ->through($this->fieldMiddleware($fieldDefinitionNode))
            ->via('handleField')
            ->then(static function (FieldValue $fieldValue): FieldValue {
                return $fieldValue;
            });

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $fieldValue->getReturnType(),
            'args' => $this->argumentFactory->toTypeMap(
                $fieldValue->getField()->arguments
            ),
            'resolve' => $fieldValue,
            'description' => data_get($fieldDefinitionNode->description, 'value'),
            'complexity' => $fieldValue->getComplexity(),
            'deprecationReason' => ASTHelper::deprecationReason($fieldDefinitionNode),
        ];
    }

    /**
     * @return array<\Nuwave\Lighthouse\Support\Contracts\FieldMiddleware>
     */
    protected function fieldMiddleware(FieldDefinitionNode $fieldDefinitionNode): array
    {
        $globalFieldMiddleware = config('lighthouse.field_middleware');

        $directiveFieldMiddleware = $this->directiveFactory
            ->associatedOfType($fieldDefinitionNode, FieldMiddleware::class)
            ->all();

        $fieldMiddleware = array_merge($globalFieldMiddleware, $directiveFieldMiddleware);

        // Middleware resolve in reversed order
        return array_reverse($fieldMiddleware);
    }
}
