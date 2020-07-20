<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Schema\Directives\RenameArgsDirective;
use Nuwave\Lighthouse\Schema\Directives\SanitizeDirective;
use Nuwave\Lighthouse\Schema\Directives\SpreadDirective;
use Nuwave\Lighthouse\Schema\Directives\TransformArgsDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Validation\ValidateDirective;

class FieldFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\DirectiveFactory
     */
    protected $directiveFactory;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory
     */
    protected $argumentSetFactory;

    public function __construct(
        DirectiveFactory $directiveFactory,
        ArgumentFactory $argumentFactory,
        Pipeline $pipeline,
        ArgumentSetFactory $argumentSetFactory
    ) {
        $this->directiveFactory = $directiveFactory;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
        $this->argumentSetFactory = $argumentSetFactory;
    }

    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @return array Configuration array for a \GraphQL\Type\Definition\FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldDefinitionNode = $fieldValue->getField();

        // Directives have the first priority for defining a resolver for a field
        /** @var \Nuwave\Lighthouse\Support\Contracts\FieldResolver $resolverDirective */
        if ($resolverDirective = $this->directiveFactory->createSingleDirectiveOfType($fieldDefinitionNode, FieldResolver::class)) {
            $fieldValue = $resolverDirective->resolveField($fieldValue);
        } else {
            $fieldValue = $fieldValue->useDefaultResolver();
        }

        $fieldMiddleware = $this->directiveFactory->createAssociatedDirectivesOfType($fieldDefinitionNode, FieldMiddleware::class)
            // Middleware resolve in reversed order
            ->push(app(RenameArgsDirective::class))
            ->push(app(SpreadDirective::class))
            ->push(app(TransformArgsDirective::class))
            ->push(app(ValidateDirective::class))
            ->push(app(SanitizeDirective::class));

        $resolverWithMiddleware = $this->pipeline
            ->send($fieldValue)
            ->through($fieldMiddleware)
            ->via('handleField')
            ->then(function (FieldValue $fieldValue): FieldValue {
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
            'deprecationReason' => $fieldValue->getDeprecationReason(),
        ];
    }
}
