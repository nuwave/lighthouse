<?php

namespace Nuwave\Lighthouse\Schema;

use Closure;
use GraphQL\Executor\Executor;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Utils;

class ResolverProvider implements ProvidesResolver
{
    /**
     * Provide a field resolver in case no resolver directive is defined for a field.
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
                // Since we already know we are on the root type, this is either
                // query, mutation or subscription
                $parent = lcfirst($fieldValue->getParentName());
                $fieldName = $fieldValue->getFieldName();
                $proposedResolverClass = ucfirst($fieldName);

                throw new DefinitionException(<<<MESSAGE
Could not locate a field resolver for the {$parent}: {$fieldName}.

Either add a resolver directive such as @all, @find or @create or add
a resolver class through:

php artisan lighthouse:{$parent} {$proposedResolverClass}

MESSAGE
                );
            }
        }

        return Closure::fromCallable(
            Executor::getDefaultFieldResolver()
        );
    }

    /**
     * @return class-string|null
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
