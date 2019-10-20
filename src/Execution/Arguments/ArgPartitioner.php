<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use Nuwave\Lighthouse\Execution\Resolver;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use ReflectionNamedType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nuwave\Lighthouse\Execution\Arguments\ArgResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;

class ArgPartitioner
{
    use HasResolverArguments;

    public function partitionResolverInputs($root, ArgumentSet $argumentSet): array
    {
        if ($root instanceof Model) {
            $model = new \ReflectionClass($root);
        }

        $before = [];
        $regular = [];
        $after = [];

        foreach ($argumentSet->arguments as $name => $argument) {
            if (
                $resolver = $argument->directives->first(function(Directive $directive): bool {
                    return $directive instanceof Resolver;
                })
            ) {
                $argument->resolver = $resolver;
                if ($resolver instanceof ResolveNestedBefore) {
                    $before[$name] = $argument;
                } elseif ($resolver instanceof ResolveNestedAfter) {
                    $after[$name] = $argument;
                }
            } elseif (isset($model)) {
                if (! $model->hasMethod($name)) {
                    $regular[$name] = $argument->value;
                    continue;
                }

                $relationMethodCandidate = $model->getMethod($name);
                if (! $returnType = $relationMethodCandidate->getReturnType()) {
                    $regular[$name] = $argument->value;
                    continue;
                }

                if (! $returnType instanceof ReflectionNamedType) {
                    $regular[$name] = $argument->value;
                    continue;
                }

                $isRelation = self::makeRelationTypeMatcher($returnType->getName());

                if ($isRelation(MorphTo::class)) {
                    $argument->resolver = new ArgResolver(new NestedMorphTo($name));
                    $before[$name] = $argument;
                } elseif ($isRelation(BelongsTo::class)) {
                    $before[$name] = $argument;
                } elseif ($isRelation(HasMany::class)) {
                    $argument->resolver = new ArgResolver(new NestedOneToMany($name));
                }
            } else {
                $regular[$name] = $argument;
            }
        }

        return [
            $before,
            $regular,
            $after,
        ];
    }

    protected static function makeRelationTypeMatcher(string $returnTypeName): \Closure
    {
        return static function (string $class) use ($returnTypeName): bool {
            return is_a($returnTypeName, $class, true);
        };
    }

    public function makeNestedResolvers()
    {
        [$before, $regular, $after] = $this->partitionResolverInputs();

        // Prepare a callback that is passed into the field resolver
        // It should be called with the new root object
        $resolveBeforeResolvers = function ($root) use ($before) {
            /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $beforeArg */
            foreach ($before as $beforeArg) {
                // TODO we might continue to automatically wrap the types in ArgResolvers,
                // but we would have to deal with non-null and list types

                ($beforeArg->resolver)($root, $beforeArg->value, $this->context, $this->resolveInfo);
            }
        };

        $resolveAfterResolvers = function ($root) use ($after) {
            /** @var \Nuwave\Lighthouse\Execution\Arguments\Argument $afterArg */
            foreach ($after as $afterArg) {
                ($afterArg->resolver)($root, $afterArg->value, $this->context, $this->resolveInfo);
            }
        };

        return [
            $resolveBeforeResolvers,
            $regular,
            $resolveAfterResolvers,
        ];
    }
}
