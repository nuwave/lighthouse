<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Pipeline\Pipeline;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ComplexityResolverDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;

class FieldFactory
{
    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

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
        $this->directiveLocator = $directiveLocator;
        $this->argumentFactory = $argumentFactory;
        $this->pipeline = $pipeline;
        $this->argumentSetFactory = $argumentSetFactory;
    }

    /**
     * Convert a FieldValue to an executable FieldDefinition.
     *
     * @return array<string, mixed> Configuration array for @see \GraphQL\Type\Definition\FieldDefinition
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldDefinitionNode = $fieldValue->getField();

        // Directives have the first priority for defining a resolver for a field
        $resolverDirective = $this->directiveLocator->exclusiveOfType($fieldDefinitionNode, FieldResolver::class);
        if ($resolverDirective instanceof FieldResolver) {
            $fieldValue = $resolverDirective->resolveField($fieldValue);
        } else {
            $fieldValue->setResolver(static::defaultResolver($fieldValue));
        }

        // Middleware resolve in reversed order

        $globalFieldMiddleware = array_reverse(
            config('lighthouse.field_middleware')
        );

        $fieldMiddleware = $this->directiveLocator
            ->associatedOfType($fieldDefinitionNode, FieldMiddleware::class)
            ->reverse()
            ->all();

        $resolverWithMiddleware = $this->pipeline
            ->send($fieldValue)
            ->through(array_merge($fieldMiddleware, $globalFieldMiddleware))
            ->via('handleField')
            // TODO replace when we cut support for Laravel 5.6
            // ->thenReturn()
            ->then(static function (FieldValue $fieldValue): FieldValue {
                return $fieldValue;
            })
            ->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolverWithMiddleware) {
            $resolveInfo->argumentSet = $this->argumentSetFactory->fromResolveInfo($args, $resolveInfo);

            return $resolverWithMiddleware($root, $args, $context, $resolveInfo);
        });

        // To see what is allowed here, look at the validation rules in
        // GraphQL\Type\Definition\FieldDefinition::getDefinition()
        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->type($fieldDefinitionNode),
            'args' => $this->argumentFactory->toTypeMap(
                $fieldValue->getField()->arguments
            ),
            'resolve' => $fieldValue->getResolver(),
            'description' => $fieldDefinitionNode->description->value ?? null,
            'complexity' => $this->complexity($fieldValue),
            'deprecationReason' => ASTHelper::deprecationReason($fieldDefinitionNode),
            'astNode' => $fieldDefinitionNode,
        ];
    }

    /**
     * @return \Closure(): (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\OutputType)
     */
    protected function type(FieldDefinitionNode $fieldDefinition): \Closure
    {
        return static function () use ($fieldDefinition) {
            /** @var \Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter $typeNodeConverter */
            $typeNodeConverter = app(ExecutableTypeNodeConverter::class);

            return $typeNodeConverter->convert($fieldDefinition->type);
        };
    }

    protected function complexity(FieldValue $fieldValue): ?callable
    {
        /** @var \Nuwave\Lighthouse\Support\Contracts\ComplexityResolverDirective|null $complexityDirective */
        $complexityDirective = $this->directiveLocator->exclusiveOfType(
            $fieldValue->getField(),
            ComplexityResolverDirective::class
        );

        if (null === $complexityDirective) {
            return null;
        }

        return $complexityDirective->complexityResolver($fieldValue);
    }

    public static function defaultResolver(FieldValue $fieldValue): callable
    {
        if (RootType::SUBSCRIPTION === $fieldValue->getParentName()) {
            /** @var \Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver $providesSubscriptionResolver */
            $providesSubscriptionResolver = app(ProvidesSubscriptionResolver::class);

            return $providesSubscriptionResolver->provideSubscriptionResolver($fieldValue);
        }
        /** @var \Nuwave\Lighthouse\Support\Contracts\ProvidesResolver $providesResolver */
        $providesResolver = app(ProvidesResolver::class);

        return $providesResolver->provideResolver($fieldValue);
    }
}
