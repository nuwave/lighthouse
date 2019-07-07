<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\TypeExtensionNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Illuminate\Routing\MiddlewareNameResolver;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;
use Nuwave\Lighthouse\Support\Compatibility\MiddlewareAdapter;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;

class MiddlewareDirective extends BaseDirective implements FieldMiddleware, TypeManipulator, TypeExtensionManipulator
{
    /**
     * todo remove as soon as name() is static itself.
     * @var string
     */
    const NAME = 'middleware';

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

    /**
     * Create a new middleware directive instance.
     *
     * @param  \Nuwave\Lighthouse\Support\Pipeline  $pipeline
     * @param  \Nuwave\Lighthouse\Support\Contracts\CreatesContext  $createsContext
     * @param  \Nuwave\Lighthouse\Support\Compatibility\MiddlewareAdapter  $middlewareAdapter
     * @return void
     */
    public function __construct(Pipeline $pipeline, CreatesContext $createsContext, MiddlewareAdapter $middlewareAdapter)
    {
        $this->pipeline = $pipeline;
        $this->createsContext = $createsContext;
        $this->middlewareAdapter = $middlewareAdapter;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @param  \Closure  $next
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
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
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\TypeDefinitionNode  $typeDefinition
     * @return void
     */
    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        self::addMiddlewareDirectiveToFields($typeDefinition);
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode  $objectType
     * @return void
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public function addMiddlewareDirectiveToFields(&$objectType): void
    {
        if (
            ! $objectType instanceof ObjectTypeDefinitionNode
            && ! $objectType instanceof ObjectTypeExtensionNode
        ) {
            throw new DirectiveException(
                'The '.self::NAME.' directive may only be placed on fields or object types.'
            );
        }

        $middlewareArgValue = (new Collection($this->directiveArgValue('checks')))
            ->map(function (string $middleware) : string {
                // Add slashes, as re-parsing of the values removes a level of slashes
                return addslashes($middleware);
            })
            ->implode('", "');

        $middlewareDirective = PartialParser::directive("@middleware(checks: [\"$middlewareArgValue\"])");

        /** @var FieldDefinitionNode $fieldDefinition */
        foreach ($objectType->fields as $fieldDefinition) {
            // If the field already has middleware defined, skip over it
            // Field middleware are more specific then those defined on a type
            if (ASTHelper::directiveDefinition($fieldDefinition, self::NAME)) {
                return;
            }

            $fieldDefinition->directives = $fieldDefinition->directives->merge([$middlewareDirective]);
        }
    }

    /**
     * Apply manipulations from a type definition node.
     *
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @param  \GraphQL\Language\AST\TypeExtensionNode  $typeExtension
     * @return void
     */
    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension): void
    {
        self::addMiddlewareDirectiveToFields($typeExtension);
    }
}
