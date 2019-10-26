<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use ReflectionClass;
use ReflectionNamedType;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\ArgumentResolver;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ArgPartitioner
{
    /**
     * @param  $root
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $argumentSet
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet[]
     */
    public function partitionResolverInputs($root, ArgumentSet $argumentSet): array
    {
        $model = $root instanceof Model
            ? new \ReflectionClass($root)
            : null;

        return self::partition(
            $argumentSet,
            static function (string $name, Argument $argument) use ($model): bool {
                $resolverDirective = $argument->directives->first(function (Directive $directive): bool {
                    return $directive instanceof ArgumentResolver;
                });

                if ($resolverDirective) {
                    $argument->resolver = $resolverDirective;

                    return true;
                }

                if (isset($model)) {
                    $isRelation = static function (string $relationClass) use ($model, $name) {
                        return self::methodReturnsRelation($model, $name, $relationClass);
                    };

                    if (
                        $isRelation(HasOne::class)
                        || $isRelation(MorphOne::class)
                    ) {
                        $argument->resolver = new ArgResolver(new NestedOneToOne($name));

                        return true;
                    }

                    if (
                        $isRelation(HasMany::class)
                        || $isRelation(MorphMany::class)
                    ) {
                        $argument->resolver = new ArgResolver(new NestedOneToMany($name));

                        return true;
                    }

                    if (
                        $isRelation(BelongsToMany::class)
                        || $isRelation(MorphToMany::class)
                    ) {
                        $argument->resolver = new ArgResolver(new NestedManyToMany($name));

                        return true;
                    }
                }

                return false;
            }
        );
    }

    protected static function partition(ArgumentSet $argumentSet, \Closure $predicate)
    {
        $regular = new ArgumentSet();
        $nested = new ArgumentSet();

        foreach ($argumentSet->arguments as $name => $argument) {
            if ($predicate($name, $argument)) {
                $nested->arguments[$name] = $argument;
                continue;
            }

            $regular->arguments[$name] = $argument;
        }

        return [
            $regular,
            $nested,
        ];
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
     * and the model has a method "comments" that returns a HasMany relationship,
     * the result will be:
     * [
     *   [
     *    'name' => 'Ralf',
     *   ]
     *   [
     *    'comments' =>
     *      ['foo' => 'Bar'],
     *   ],
     * ]
     *
     * @param  \ReflectionClass  $modelReflection
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet  $argumentSet
     * @param  string  $relationClass
     * @return \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet[]  [remainingArgs, relationshipArgs]
     */
    public static function partitionByRelationType(
        ReflectionClass $modelReflection,
        ArgumentSet $argumentSet,
        string $relationClass
    ): array {
        return self::partition(
            $argumentSet,
            static function (string $name) use ($modelReflection, $relationClass): bool {
                return self::methodReturnsRelation($modelReflection, $name, $relationClass);
            }
        );
    }

    public static function methodReturnsRelation(
        ReflectionClass $modelReflection,
        string $name,
        string $relationClass
    ): bool {
        if (! $modelReflection->hasMethod($name)) {
            return false;
        }

        $relationMethodCandidate = $modelReflection->getMethod($name);
        if (! $returnType = $relationMethodCandidate->getReturnType()) {
            return false;
        }

        if (! $returnType instanceof ReflectionNamedType) {
            return false;
        }

        return is_a($returnType->getName(), $relationClass, true);
    }
}
