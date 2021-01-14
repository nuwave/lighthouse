<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Pipeline\Pipeline;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
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
            $fieldValue = $resolverDirective->resolveField($fieldValue);
        } else {
            $fieldValue = $fieldValue->useDefaultResolver();
        }

        // Middleware resolve in reversed order

        $globalFieldMiddleware = array_reverse(
            config('lighthouse.field_middleware')
        );

        $fieldMiddleware = $this->directiveFactory
            ->associatedOfType($fieldDefinitionNode, FieldMiddleware::class)
            ->reverse()
            ->all();

        $resolverWithMiddleware = $this->pipeline
            ->send($fieldValue)
            ->through(array_merge($fieldMiddleware, $globalFieldMiddleware))
            ->via('handleField')
            // TODO replace when we cut support for Laravel 5.6
            //->thenReturn()
            ->then(static function (FieldValue $fieldValue): FieldValue {
                return $fieldValue;
            })
            ->getResolver();

        $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolverWithMiddleware) {
                $resolveInfo->argumentSet = $this->argumentSetFactory->fromResolveInfo($args, $resolveInfo);

                return $resolverWithMiddleware($root, $args, $context, $resolveInfo);
            }
        );

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $fieldValue->getReturnType(),
            'args' => $this->argumentFactory->toTypeMap(
                $fieldValue->getField()->arguments
            ),
            'resolve' => $fieldValue->getResolver(),
            'description' => data_get($fieldDefinitionNode->description, 'value'),
            'complexity' => $fieldValue->getComplexity(),
            'deprecationReason' => ASTHelper::deprecationReason($fieldDefinitionNode),
        ];
    }
}
