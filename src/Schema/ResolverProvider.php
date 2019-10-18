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
            // TODO use only __invoke in v5
            $resolverClass = $this->findResolverClass($fieldValue, 'resolve');
            if ($resolverClass) {
                return Closure::fromCallable(
                    [app($resolverClass), 'resolve']
                );
            }

            $resolverClass = $this->findResolverClass($fieldValue, '__invoke');
            if ($resolverClass) {
                return Closure::fromCallable(
                    [app($resolverClass), '__invoke']
                );
            }

            if (! $resolverClass) {
                throw new DefinitionException(
                    "Could not locate a default resolver for the field {$fieldValue->getFieldName()}"
                );
            }
        }

        return Closure::fromCallable(
            Executor::getDefaultFieldResolver()
        );
    }

    /**
     * @param  FieldValue  $fieldValue
     * @param  string  $methodName
     * @return string|null
     */
    protected function findResolverClass(FieldValue $fieldValue, string $methodName): ?string
    {
        return Utils::namespaceClassname(
            Str::studly($fieldValue->getFieldName()),
            $fieldValue->defaultNamespacesForParent(),
            function (string $class) use ($methodName): bool {
                return method_exists($class, $methodName);
            }
        );
    }
}
