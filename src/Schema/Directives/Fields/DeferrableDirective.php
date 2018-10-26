<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Extensions\DeferExtension;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Schema\Extensions\ExtensionRegistry;

class DeferrableDirective extends BaseDirective implements Directive, FieldMiddleware
{
    /** @var ExtensionRegistry */
    protected $extensions;

    /**
     * @param ExtensionRegistry $extensions
     */
    public function __construct(ExtensionRegistry $extensions)
    {
        $this->extensions = $extensions;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name()
    {
        return 'deferrable';
    }

    /**
     * Resolve the field directive.
     *
     * @param FieldValue $value
     * @param \Closure   $next
     *
     * @return FieldValue
     */
    public function handleField(FieldValue $value, \Closure $next)
    {
        $resolver = $value->getResolver();
        $value->setResolver(
            function ($root, $args, $context, ResolveInfo $info) use ($resolver) {
                $path = implode('.', $info->path);
                $extension = $this->getDeferExtension();

                if (ASTHelper::fieldHasDirective($info->fieldNodes[0], 'defer')) {
                    return $extension->defer(
                        function () use ($resolver, $root, $args, $context, $info) {
                            return $resolver($root, $args, $context, $info);
                        },
                        $path
                    );
                }

                return $extension->isStreaming()
                    ? $extension->findOrResolve(function () use ($resolver, $root, $args, $context, $info) {
                        return $resolver($root, $args, $context, $info);
                    }, $path)
                    : $resolver($root, $args, $context, $info);
            }
        );

        return $next($value);
    }

    /**
     * @return DeferExtension
     */
    protected function getDeferExtension(): DeferExtension
    {
        return $this->extensions->get(DeferExtension::name());
    }
}
