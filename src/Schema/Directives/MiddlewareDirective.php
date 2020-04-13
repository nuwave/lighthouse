<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Compatibility\MiddlewareAdapter;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Pipeline;

/**
 * @deprecated Will be removed in v5
 */
class MiddlewareDirective extends BaseDirective implements FieldMiddleware, TypeManipulator, TypeExtensionManipulator, DefinedDirective
{
    /**
     * @var \Nuwave\Lighthouse\Support\Pipeline
     */
    protected $pipeline;

    /**
     * @var \Nuwave\Lighthouse\Support\Contracts\CreatesContext
     */
    protected $createsContext;

    /**
     * @var \Nuwave\Lighthouse\Support\Compatibility\MiddlewareAdapter
     */
    private $middlewareAdapter;

    public function __construct(Pipeline $pipeline, CreatesContext $createsContext, MiddlewareAdapter $middlewareAdapter)
    {
        $this->pipeline = $pipeline;
        $this->createsContext = $createsContext;
        $this->middlewareAdapter = $middlewareAdapter;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'SDL'
"""
Run Laravel middleware for a specific field or group of fields.
This can be handy to reuse existing HTTP middleware.
"""
directive @middleware(
  """
  Specify which middleware to run.
  Pass in either a fully qualified class name, an alias or
  a middleware group - or any combination of them.
  """
  checks: [String!]
) on FIELD_DEFINITION | OBJECT
SDL;
    }

    /**
     * Resolve the field directive.
     */
    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $middleware = $this->getQualifiedMiddlewareNames(
            $this->directiveArgValue('checks')
        );
        $resolver = $fieldValue->getResolver();

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $middleware) {
                    return $this->pipeline
                        ->send($context->request())
                        ->through($middleware)
                        ->then(function (Request $request) use ($resolver, $root, $args, $resolveInfo) {
                            return $resolver(
                                $root,
                                $args,
                                $this->createsContext->generate($request),
                                $resolveInfo
                            );
                        });
                }
            )
        );
    }

    /**
     * @param  string|string[]  $middlewareArgValue
     * @return \Illuminate\Support\Collection<string>
     */
    protected function getQualifiedMiddlewareNames($middlewareArgValue): Collection
    {
        $middleware = $this->middlewareAdapter->getMiddleware();
        $middlewareGroups = $this->middlewareAdapter->getMiddlewareGroups();

        return (new Collection($middlewareArgValue))
            ->map(function (string $name) use ($middleware, $middlewareGroups): array {
                return (array) MiddlewareNameResolver::resolve($name, $middleware, $middlewareGroups);
            })
            ->flatten();
    }

    /**
     * Apply manipulations from a type definition node.
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        $this->addMiddlewareDirectiveToFields($typeDefinition);
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode  $objectType
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    protected function addMiddlewareDirectiveToFields(&$objectType): void
    {
        $middlewareArgValue = (new Collection($this->directiveArgValue('checks')))
            ->map(function (string $middleware): string {
                // Add slashes, as re-parsing of the values removes a level of slashes
                return addslashes($middleware);
            })
            ->implode('", "');

        $middlewareDirective = PartialParser::directive("@middleware(checks: [\"$middlewareArgValue\"])");

        ASTHelper::addDirectiveToFields($middlewareDirective, $objectType);
    }

    /**
     * Apply manipulations from a type definition node.
     */
    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension): void
    {
        $this->addMiddlewareDirectiveToFields($typeExtension);
    }
}
