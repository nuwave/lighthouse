<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\Directives\NestDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;
use Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver;
use Nuwave\Lighthouse\Support\Utils;

class ArgPartitioner
{
    /**
     * Partition the arguments into nested and regular.
     *
     * @return array{
     *   0: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     *   1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     * }
     */
    public static function nestedArgResolvers(ArgumentSet $argumentSet, mixed $root): array
    {
        $model = $root instanceof Model
            ? new \ReflectionClass($root)
            : null;

        foreach ($argumentSet->arguments as $name => $argument) {
            static::attachNestedArgResolver($name, $argument, $model);
        }

        return static::partition(
            $argumentSet,
            static fn (string $name, Argument $argument): bool => isset($argument->resolver),
        );
    }

    /**
     * Like nestedArgResolvers(), but excludes SaveAwareArgResolvers that run before save.
     *
     * Used by SaveModel's ResolveNested wrapper so pre-save resolvers stay in the
     * regular set and reach SaveModel for execution before $model->save().
     *
     * @return array{
     *   0: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     *   1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     * }
     */
    public static function nestedArgResolversWithoutPreSave(ArgumentSet $argumentSet, mixed $root): array
    {
        $model = $root instanceof Model
            ? new \ReflectionClass($root)
            : null;

        foreach ($argumentSet->arguments as $name => $argument) {
            static::attachNestedArgResolver($name, $argument, $model);
        }

        [$nested, $regular] = static::partition(
            $argumentSet,
            static function (string $name, Argument $argument) use ($root, $model): bool {
                $resolver = $argument->resolver;
                if ($resolver === null) {
                    return false;
                }

                if ($model === null) {
                    return true;
                }

                assert($root instanceof Model);

                if ($resolver instanceof SaveAwareArgResolver) {
                    return ! $resolver->runBeforeSave($root);
                }

                return true;
            },
        );

        if ($model !== null) {
            assert($root instanceof Model);
            static::liftPreSaveResolversFromNest($nested, $regular, $root, $model);
        }

        return [$nested, $regular];
    }

    /**
     * Recursively traverse @nest arguments and lift pre-save resolvers to the regular set
     * so they reach SaveModel and execute before $model->save().
     *
     * @param  \ReflectionClass<\Illuminate\Database\Eloquent\Model>  $model
     */
    protected static function liftPreSaveResolversFromNest(ArgumentSet $nested, ArgumentSet $regular, Model $root, \ReflectionClass $model): void
    {
        foreach ($nested->arguments as $argument) {
            if (! $argument->resolver instanceof NestDirective) {
                continue;
            }

            $nestValue = $argument->value;
            if (! $nestValue instanceof ArgumentSet) {
                continue;
            }

            foreach ($nestValue->arguments as $childName => $childArgument) {
                static::attachNestedArgResolver($childName, $childArgument, $model);

                if ($childArgument->resolver instanceof SaveAwareArgResolver && $childArgument->resolver->runBeforeSave($root)) {
                    $regular->arguments[$childName] = $childArgument;
                    unset($nestValue->arguments[$childName]);
                } elseif ($childArgument->resolver instanceof NestDirective) {
                    $childNested = new ArgumentSet();
                    $childNested->arguments[$childName] = $childArgument;
                    static::liftPreSaveResolversFromNest($childNested, $regular, $root, $model);
                }
            }
        }
    }

    /**
     * Requires that attachNestedArgResolver() has run on the arguments first.
     *
     * @return array{
     *   0: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     *   1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet,
     * }
     */
    public static function preSaveNestedArgResolvers(ArgumentSet $argumentSet, Model $model): array
    {
        return static::partition(
            $argumentSet,
            static fn (string $name, Argument $argument): bool => $argument->resolver instanceof SaveAwareArgResolver
                && $argument->resolver->runBeforeSave($model),
        );
    }

    /**
     * Extract all the arguments that correspond to a relation of a certain type on the model.
     *
     * For example, if the args input looks like this:
     *
     * [
     *  'name' => 'Ralf',
     *  'comments' =>
     *    ['foo' => 'Bar'],
     * ]
     *
     * and the model has a method "comments" that returns a HasMany relationship, the result will be:
     * [
     *   [
     *    'comments' =>
     *      ['foo' => 'Bar'],
     *   ],
     *   [
     *    'name' => 'Ralf',
     *   ]
     * ]
     *
     * @return array{0: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet, 1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet}
     */
    public static function relationMethods(
        ArgumentSet $argumentSet,
        Model $model,
        string $relationClass,
    ): array {
        $modelReflection = new \ReflectionClass($model);

        [$relations, $remaining] = static::partition(
            $argumentSet,
            static fn (string $name): bool => static::methodReturnsRelation($modelReflection, $name, $relationClass),
        );

        $nonNullRelations = new ArgumentSet();
        $nonNullRelations->arguments = array_filter(
            $relations->arguments,
            static fn (Argument $argument): bool => $argument->value !== null,
        );

        return [$nonNullRelations, $remaining];
    }

    /**
     * Attach a nested argument resolver to an argument.
     *
     * @param  \ReflectionClass<\Illuminate\Database\Eloquent\Model>|null  $model
     */
    protected static function attachNestedArgResolver(string $name, Argument &$argument, ?\ReflectionClass $model): void
    {
        $resolverDirective = $argument->directives->first(
            Utils::instanceofMatcher(ArgResolver::class),
        );
        assert($resolverDirective instanceof ArgResolver || $resolverDirective === null);

        if ($resolverDirective !== null) {
            $argument->resolver = $resolverDirective;

            return;
        }

        if (isset($model)) {
            $isRelation = static fn (string $relationClass): bool => static::methodReturnsRelation($model, $name, $relationClass);

            if (
                $isRelation(HasOne::class)
                || $isRelation(MorphOne::class)
            ) {
                $argument->resolver = new ResolveNested(new NestedOneToOne($name));

                return;
            }

            if (
                $isRelation(HasMany::class)
                || $isRelation(MorphMany::class)
            ) {
                $argument->resolver = new ResolveNested(new NestedOneToMany($name));

                return;
            }

            if (
                $isRelation(BelongsToMany::class)
                || $isRelation(MorphToMany::class)
            ) {
                $argument->resolver = new ResolveNested(new NestedManyToMany($name));
            }
        }
    }

    /**
     * Partition arguments based on a predicate.
     *
     * The predicate is called for each argument within the ArgumentSet.
     * It receives the following parameters:
     * 1. The name of the argument
     * 2. The argument itself
     *
     * Returns an array of two new ArgumentSet instances:
     * - the first one contains all arguments for which the predicate matched
     * - the second one contains all arguments for which the predicate did not match
     *
     * @param  callable(string $name, \Nuwave\Lighthouse\Execution\Arguments\Argument $argument): bool  $predicate
     *
     * @return array{0: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet, 1: \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet}
     */
    public static function partition(ArgumentSet $argumentSet, callable $predicate): array
    {
        $matched = new ArgumentSet();
        $notMatched = new ArgumentSet();

        foreach ($argumentSet->arguments as $name => $argument) {
            if ($predicate($name, $argument)) {
                $matched->arguments[$name] = $argument;
            } else {
                $notMatched->arguments[$name] = $argument;
            }
        }

        return [
            $matched,
            $notMatched,
        ];
    }

    /**
     * Does a method on the model return a relation of the given class?
     *
     * @param  \ReflectionClass<\Illuminate\Database\Eloquent\Model>  $modelReflection
     */
    public static function methodReturnsRelation(
        \ReflectionClass $modelReflection,
        string $name,
        string $relationClass,
    ): bool {
        if (! $modelReflection->hasMethod($name)) {
            return false;
        }

        $relationMethodCandidate = $modelReflection->getMethod($name);

        $returnType = $relationMethodCandidate->getReturnType();
        if (! $returnType instanceof \ReflectionNamedType) {
            return false;
        }

        if ($returnType->isBuiltin()) {
            return false;
        }

        if (! class_exists($returnType->getName())) {
            throw new DefinitionException("Class {$returnType->getName()} does not exist, did you forget to import the Eloquent relation class?");
        }

        return is_a($returnType->getName(), $relationClass, true);
    }
}
