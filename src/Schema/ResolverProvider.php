<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use Illuminate\Support\Str;
use GraphQL\Executor\Executor;
use Nuwave\Lighthouse\Support\Utils;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;

class ResolverProvider implements ProvidesResolver
{
    /**
     * Provide a field resolver in case no resolver directive is defined for a field.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Closure
     *
     * @throws \Nuwave\Lighthouse\Exceptions\DefinitionException
     */
    public function provideResolver(FieldValue $fieldValue): Closure
    {
        if ($fieldValue->parentIsRootType()) {
            $resolverClass = Utils::namespaceClassname(
                Str::studly($fieldValue->getFieldName()),
                $fieldValue->defaultNamespacesForParent(),
                function (string $class): bool {
                    return method_exists($class, 'resolve');
                }
            );

            if (! $resolverClass) {
                throw new DefinitionException(
                    "Could not locate a default resolver for the field {$fieldValue->getFieldName()}"
                );
            }

            return Closure::fromCallable(
                [app($resolverClass), 'resolve']
            );
        }

        return Closure::fromCallable(
            Executor::getDefaultFieldResolver()
        );
    }
}
