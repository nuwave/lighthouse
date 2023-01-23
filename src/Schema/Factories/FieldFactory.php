<?php

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ComplexityResolverDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;

class FieldFactory
{
    /**
     * @var \Illuminate\Pipeline\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * @var \Nuwave\Lighthouse\Schema\DirectiveLocator
     */
    protected $directiveLocator;

    /**
     * @var \Nuwave\Lighthouse\Schema\Factories\ArgumentFactory
     */
    protected $argumentFactory;

    /**
     * @var \Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory
     */
    protected $argumentSetFactory;

    public function __construct(
        Pipeline $pipeline,
        ConfigRepository $config,
        DirectiveLocator $directiveLocator,
        ArgumentFactory $argumentFactory,
        ArgumentSetFactory $argumentSetFactory
    ) {
        $this->pipeline = $pipeline;
        $this->config = $config;
        $this->directiveLocator = $directiveLocator;
        $this->argumentFactory = $argumentFactory;
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

        $globalFieldMiddleware = (new Collection($this->config->get('lighthouse.field_middleware')))
            ->reverse()
            ->map(function (string $middlewareDirective): Directive {
                return Container::getInstance()->make($middlewareDirective);
            })
            ->each(function (Directive $directive) use ($fieldDefinitionNode): void {
                if ($directive instanceof BaseDirective) {
                    $directive->definitionNode = $fieldDefinitionNode;
                }
            });

        $fieldMiddleware = $this->directiveLocator
            ->associatedOfType($fieldDefinitionNode, FieldMiddleware::class)
            ->reverse();

        $resolverWithMiddleware = $this->pipeline
            ->send($fieldValue)
            ->through(array_merge($fieldMiddleware->all(), $globalFieldMiddleware->all()))
            ->via('handleField')
            // TODO replace when we cut support for Laravel 5.6
            // ->thenReturn()
            ->then(static function (FieldValue $fieldValue): FieldValue {
                return $fieldValue;
            })
            ->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, BaseResolveInfo $resolveInfo) use ($resolverWithMiddleware) {
            $wrappedResolveInfo = new ResolveInfo(
                $resolveInfo->fieldDefinition,
                $resolveInfo->fieldNodes,
                $resolveInfo->parentType,
                $resolveInfo->path,
                $resolveInfo->schema,
                $resolveInfo->fragments,
                $resolveInfo->rootValue,
                $resolveInfo->operation,
                $resolveInfo->variableValues,
                $this->argumentSetFactory->fromResolveInfo($args, $resolveInfo)
            );

            return $resolverWithMiddleware($root, $args, $context, $wrappedResolveInfo);
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
            $typeNodeConverter = Container::getInstance()->make(ExecutableTypeNodeConverter::class);
            assert($typeNodeConverter instanceof ExecutableTypeNodeConverter);

            return $typeNodeConverter->convert($fieldDefinition->type);
        };
    }

    protected function complexity(FieldValue $fieldValue): ?callable
    {
        $complexityDirective = $this->directiveLocator->exclusiveOfType(
            $fieldValue->getField(),
            ComplexityResolverDirective::class
        );

        if (null === $complexityDirective) {
            return null;
        }
        assert($complexityDirective instanceof ComplexityResolverDirective);

        return $complexityDirective->complexityResolver($fieldValue);
    }

    public static function defaultResolver(FieldValue $fieldValue): callable
    {
        if (RootType::SUBSCRIPTION === $fieldValue->getParentName()) {
            $providesSubscriptionResolver = Container::getInstance()->make(ProvidesSubscriptionResolver::class);
            assert($providesSubscriptionResolver instanceof ProvidesSubscriptionResolver);

            // @phpstan-ignore-next-line Call to method provideSubscriptionResolver() on an unknown class Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver@anonymous
            return $providesSubscriptionResolver->provideSubscriptionResolver($fieldValue);
        }

        $providesResolver = Container::getInstance()->make(ProvidesResolver::class);
        assert($providesResolver instanceof ProvidesResolver);

        return $providesResolver->provideResolver($fieldValue);
    }
}
