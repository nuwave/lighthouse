<?php

namespace Nuwave\Lighthouse\Schema\Directives\Fields;

use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\NonNullTypeNode;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Exceptions\ParseClientException;
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
        $fieldType = $value->getField()->type;

        $value->setResolver(
            function ($root, $args, $context, ResolveInfo $info) use ($resolver, $fieldType) {
                $path = implode('.', $info->path);
                $extension = $this->getDeferExtension();
                $wrappedResolver = function () use ($resolver, $root, $args, $context, $info) {
                    return $resolver($root, $args, $context, $info);
                };

                if ($this->shouldDefer($fieldType, $info)) {
                    return $extension->defer($wrappedResolver, $path);
                }

                return $extension->isStreaming()
                    ? $extension->findOrResolve($wrappedResolver, $path)
                    : $resolver($root, $args, $context, $info);
            }
        );

        return $next($value);
    }

    /**
     * Determine of field should be deferred.
     *
     * @param TypeNode    $fieldType
     * @param ResolveInfo $info
     *
     * @throws ParseClientException
     *
     * @return bool
     */
    protected function shouldDefer(TypeNode $fieldType, ResolveInfo $info): bool
    {
        if ('mutation' === strtolower($info->operation->operation)) {
            return false;
        }

        foreach ($info->fieldNodes as $fieldNode) {
            $deferDirective = ASTHelper::directiveDefinition($fieldNode, 'defer');

            if (! $deferDirective) {
                return false;
            }

            if (! ASTHelper::directiveArgValue($deferDirective, 'if', true)) {
                return false;
            }

            $skipDirective = ASTHelper::directiveDefinition($fieldNode, 'skip');
            $includeDirective = ASTHelper::directiveDefinition($fieldNode, 'include');

            $shouldSkip = $skipDirective ? ASTHelper::directiveArgValue($skipDirective, 'if', false) : false;
            $shouldInclude = $includeDirective ? ASTHelper::directiveArgValue($includeDirective, 'if', false) : false;

            if ($shouldSkip || $shouldInclude) {
                return false;
            }
        }

        if ($fieldType instanceof NonNullTypeNode) {
            throw new ParseClientException('The @defer directive cannot be placed on a Non-Nullable field.');
        }

        return true;
    }

    /**
     * @return DeferExtension
     */
    protected function getDeferExtension(): DeferExtension
    {
        return $this->extensions->get(DeferExtension::name());
    }
}
