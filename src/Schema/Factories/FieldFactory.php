<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Pipeline\Pipeline;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\OptimizingResolver;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

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

    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory
     */
    protected $argumentSetFactory;

    public function __construct(
        DirectiveLocator $directiveLocator,
        ArgumentFactory $argumentFactory,
        Pipeline $pipeline,
        ArgumentSetFactory $argumentSetFactory
    ) {
        $this->directiveFactory = $directiveLocator;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
        $this->argumentSetFactory = $argumentSetFactory;
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

        // Do this after applying other field middleware, so before them in terms of execution order
        $previousOneOffResolver = $fieldValue->getOneOffResolver();
        $oneOffResolver = function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousOneOffResolver) {
            $resolveInfo->argumentSet = $this->argumentSetFactory->fromResolveInfo($args, $resolveInfo);

            return $previousOneOffResolver($root, $args, $context, $resolveInfo);
        };

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $fieldValue->getReturnType(),
            'args' => $this->argumentFactory->toTypeMap(
                $fieldValue->getField()->arguments
            ),
            'resolve' => new OptimizingResolver($oneOffResolver, $fieldValue->getResolver()),
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
