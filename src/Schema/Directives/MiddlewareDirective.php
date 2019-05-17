<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use Illuminate\Http\Request;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
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
use Nuwave\Lighthouse\Support\Contracts\NodeManipulator;
use Nuwave\Lighthouse\Support\Compatibility\MiddlewareAdapter;

class MiddlewareDirective extends BaseDirective implements FieldMiddleware, NodeManipulator
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
        return 'middleware';
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
     * @param  \GraphQL\Language\AST\Node  $node
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(Node $node, DocumentAST $documentAST): DocumentAST
    {
        return $documentAST->setDefinition(
            self::addMiddlewareDirectiveToFields(
                $node,
                $this->directiveArgValue('checks')
            )
        );
    }

    /**
     * @param  \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode  $objectType
     * @param  array  $middlewareArgValue
     * @return \GraphQL\Language\AST\ObjectTypeDefinitionNode|\GraphQL\Language\AST\ObjectTypeExtensionNode
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DirectiveException
     */
    public static function addMiddlewareDirectiveToFields($objectType, $middlewareArgValue)
    {
        if (
            ! $objectType instanceof ObjectTypeDefinitionNode
            && ! $objectType instanceof ObjectTypeExtensionNode
        ) {
            throw new DirectiveException(
                'The '.self::NAME.' directive may only be placed on fields or object types.'
            );
        }

        $middlewareArgValue = (new Collection($middlewareArgValue))
            ->map(function (string $middleware) : string {
                // Add slashes, as re-parsing of the values removes a level of slashes
                return addslashes($middleware);
            })
            ->implode('", "');

        $middlewareDirective = PartialParser::directive("@middleware(checks: [\"$middlewareArgValue\"])");

        $objectType->fields = new NodeList(
            (new Collection($objectType->fields))
                ->map(function (FieldDefinitionNode $fieldDefinition) use ($middlewareDirective): FieldDefinitionNode {
                    // If the field already has middleware defined, skip over it
                    // Field middleware are more specific then those defined on a type
                    if (ASTHelper::directiveDefinition($fieldDefinition, self::NAME)) {
                        return $fieldDefinition;
                    }

                    $fieldDefinition->directives = $fieldDefinition->directives->merge([$middlewareDirective]);

                    return $fieldDefinition;
                })
                ->all()
        );

        return $objectType;
    }
}
