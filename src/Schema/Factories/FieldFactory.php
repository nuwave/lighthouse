<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Factories;

use GraphQL\Language\AST\FieldDefinitionNode;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSetFactory;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\ExecutableTypeNodeConverter;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\RootType;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ComplexityResolverDirective;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Contracts\ProvidesSubscriptionResolver;

/**
 * @phpstan-import-type FieldResolver from \GraphQL\Executor\Executor as FieldResolverFn
 * @phpstan-import-type FieldDefinitionConfig from \GraphQL\Type\Definition\FieldDefinition
 * @phpstan-import-type FieldType from \GraphQL\Type\Definition\FieldDefinition
 * @phpstan-import-type ComplexityFn from \GraphQL\Type\Definition\FieldDefinition
 */
class FieldFactory
{
    public function __construct(
        protected ConfigRepository $config,
        protected DirectiveLocator $directiveLocator,
        protected ArgumentFactory $argumentFactory,
        protected ArgumentSetFactory $argumentSetFactory,
    ) {}

    /**
     * Convert a FieldValue to a config for an executable FieldDefinition.
     *
     * @return FieldDefinitionConfig
     */
    public function handle(FieldValue $fieldValue): array
    {
        $fieldDefinitionNode = $fieldValue->getField();

        // Directives have the first priority for defining a resolver for a field
        $resolverDirective = $this->directiveLocator->exclusiveOfType($fieldDefinitionNode, FieldResolver::class);
        $resolver = $resolverDirective instanceof FieldResolver
            ? $resolverDirective->resolveField($fieldValue)
            : $this->defaultResolver($fieldValue);

        foreach ($this->fieldMiddleware($fieldDefinitionNode) as $fieldMiddleware) {
            $fieldMiddleware->handleField($fieldValue);
        }

        return [
            'name' => $fieldDefinitionNode->name->value,
            'type' => $this->type($fieldDefinitionNode),
            'args' => $this->argumentFactory->toTypeMap(
                $fieldValue->getField()->arguments,
            ),
            'resolve' => $fieldValue->finishResolver($resolver),
            'description' => $fieldDefinitionNode->description->value ?? null,
            'complexity' => $this->complexity($fieldValue),
            'deprecationReason' => ASTHelper::deprecationReason($fieldDefinitionNode),
            'astNode' => $fieldDefinitionNode,
        ];
    }

    /** @return array<\Nuwave\Lighthouse\Support\Contracts\FieldMiddleware> */
    protected function fieldMiddleware(FieldDefinitionNode $fieldDefinitionNode): array
    {
        $globalFieldMiddleware = (new Collection($this->config->get('lighthouse.field_middleware')))
            ->map(static fn (string $middlewareDirective): Directive => Container::getInstance()->make($middlewareDirective))
            ->each(static function (Directive $directive) use ($fieldDefinitionNode): void {
                if ($directive instanceof BaseDirective) {
                    $directive->definitionNode = $fieldDefinitionNode;
                }
            })
            ->all();

        $directiveFieldMiddleware = $this->directiveLocator
            ->associatedOfType($fieldDefinitionNode, FieldMiddleware::class)
            ->all();

        // @phpstan-ignore-next-line PHPStan does not get this list is filtered for FieldMiddleware
        return array_merge($globalFieldMiddleware, $directiveFieldMiddleware);
    }

    /** @return \Closure(): (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\OutputType) */
    protected function type(FieldDefinitionNode $fieldDefinition): \Closure
    {
        return static function () use ($fieldDefinition) {
            $typeNodeConverter = Container::getInstance()->make(ExecutableTypeNodeConverter::class);

            return $typeNodeConverter->convert($fieldDefinition->type);
        };
    }

    /** @return ComplexityFn|null */
    protected function complexity(FieldValue $fieldValue): ?callable
    {
        $complexityDirective = $this->directiveLocator->exclusiveOfType(
            $fieldValue->getField(),
            ComplexityResolverDirective::class,
        );

        return $complexityDirective instanceof ComplexityResolverDirective
            ? $complexityDirective->complexityResolver($fieldValue)
            : null;
    }

    /** @return FieldResolverFn */
    protected function defaultResolver(FieldValue $fieldValue): callable
    {
        if ($fieldValue->getParentName() === RootType::SUBSCRIPTION) {
            /** @var ProvidesSubscriptionResolver $providesSubscriptionResolver */
            $providesSubscriptionResolver = Container::getInstance()->make(ProvidesSubscriptionResolver::class);

            return $providesSubscriptionResolver->provideSubscriptionResolver($fieldValue);
        }

        /** @var ProvidesResolver $providesResolver */
        $providesResolver = Container::getInstance()->make(ProvidesResolver::class);

        return $providesResolver->provideResolver($fieldValue);
    }
}
