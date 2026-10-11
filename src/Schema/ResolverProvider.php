<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema;

use GraphQL\Executor\Executor;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ProvidesResolver;
use Nuwave\Lighthouse\Support\Utils;

class ResolverProvider implements ProvidesResolver
{
    /** Provide a field resolver in case no resolver directive is defined for a field. */
    public function provideResolver(FieldValue $fieldValue): \Closure
    {
        $resolverClass = $this->findResolverClass($fieldValue, '__invoke');

        if ($resolverClass === null) {
            $documentAST = $fieldValue->getParent()->getDocumentAST();
            if ($documentAST->isRootType($fieldValue->getParentName())) {
                $this->throwMissingResolver($fieldValue);
            }

            // Return any non-null value to continue nested field resolution
            // when the root Query type is returned as part of the result.
            if (ASTHelper::getUnderlyingTypeName($fieldValue->getField()) === $documentAST->rootTypes[RootType::QUERY]) {
                return static fn (): bool => true;
            }

            return \Closure::fromCallable(
                Executor::getDefaultFieldResolver(),
            );
        }

        $resolver = Container::getInstance()->make($resolverClass);
        assert(is_object($resolver));

        return \Closure::fromCallable([$resolver, '__invoke']);
    }

    /** @return class-string|null */
    protected function findResolverClass(FieldValue $fieldValue, string $methodName): ?string
    {
        return Utils::namespaceClassname(
            Str::studly($fieldValue->getFieldName()),
            $fieldValue->parentNamespaces(),
            static fn (string $class): bool => method_exists($class, $methodName),
        );
    }

    /** @return never */
    protected function throwMissingResolver(FieldValue $fieldValue): void
    {
        $documentAST = $fieldValue->getParent()->getDocumentAST();
        $defaultRootTypeName = $documentAST->defaultRootTypeName($fieldValue->getParentName());
        assert($defaultRootTypeName !== null, 'only called for fields of root types');
        $parent = lcfirst($defaultRootTypeName);
        $fieldName = $fieldValue->getFieldName();
        $proposedResolverClass = ucfirst($fieldName);

        throw new DefinitionException(<<<MESSAGE
        Could not locate a field resolver for the {$parent} field "{$fieldName}".

        Either annotate the field with a resolver directive such as @all, @find or @create,
        or create a resolver class through:

        php artisan lighthouse:{$parent} {$proposedResolverClass}

        MESSAGE);
    }
}
