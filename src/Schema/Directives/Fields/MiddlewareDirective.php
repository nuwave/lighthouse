<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Pipeline;
use Illuminate\Routing\MiddlewareNameResolver;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\CreatesContext;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;

class MiddlewareDirective extends BaseDirective implements FieldMiddleware
{
    /** @var string todo remove as soon as name() is static itself */
    const NAME = 'middleware';

    /** @var Pipeline */
    protected $pipeline;
    /** @var CreatesContext */
    protected $createsContext;

    /**
     * @param Pipeline $pipeline
     * @param CreatesContext $createsContext
     */
    public function __construct(Pipeline $pipeline, CreatesContext $createsContext)
    {
        $this->pipeline = $pipeline;
        $this->createsContext = $createsContext;
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
     * @param FieldValue $value
     * @param \Closure    $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next): FieldValue
    {
        /** @var Router $router */
        $router = resolve('router');
        $middleware = $router->getMiddleware();
        $middlewareGroups = $router->getMiddlewareGroups();

        $middleware = collect($this->directiveArgValue('checks'))
            ->map(function ($name) use ($middleware, $middlewareGroups){
                return (array) MiddlewareNameResolver::resolve($name, $middleware, $middlewareGroups);
            })
            ->flatten();

        $resolver = $value->getResolver();

        return $next(
            $value->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $middleware) {
                    dump($resolveInfo->fieldName, $middleware);
                    return $this->pipeline
                        ->send(
                            Request::createFrom(
                                $context->request()
                            )
                            // Duplicate the request so we have independent lifecycles for the fields
//                            $context->request()
//                                ->instance()
//                                ->duplicate()
                        )
                        ->through(
                            $middleware
                        )
                        ->then(function (Request $request) use ($resolver, $root, $args, $resolveInfo){
                            dump(data_get($request,'foo'));
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
}
