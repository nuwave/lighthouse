<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use ReflectionNamedType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nuwave\Lighthouse\Schema\Directives\ArgResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;

class ArgPartitioner
{
    use HasResolverArguments;

    public function partitionResolverInputs(): array
    {
        if ($this->root instanceof Model) {
            $model = new \ReflectionClass($this->root);
        }

        $before = [];
        $regular = [];
        $after = [];

        // TODO deal with @spread arguments
        $typedArgs = new TypedArgs($this->args, $this->resolveInfo->fieldDefinition->args);

        /**
         * @var string
         * @var \Nuwave\Lighthouse\Execution\Arguments\TypedArg $typedArg
         */
        foreach ($typedArgs as $name => $typedArg) {
            if ($resolver = $typedArg->resolver) {
                if ($resolver instanceof ResolveNestedBefore) {
                    $before[$name] = $typedArg;
                } elseif ($resolver instanceof ResolveNestedAfter) {
                    $after[$name] = $typedArg;
                }
            } elseif (isset($model)) {
                if (! $model->hasMethod($name)) {
                    $regular[$name] = $typedArg->value;
                    continue;
                }

                $relationMethodCandidate = $model->getMethod($name);
                if (! $returnType = $relationMethodCandidate->getReturnType()) {
                    $regular[$name] = $typedArg->value;
                    continue;
                }

                if (! $returnType instanceof ReflectionNamedType) {
                    $regular[$name] = $typedArg->value;
                    continue;
                }

                $returnTypeName = $returnType->getName();
                $isRelation = static function (string $class) use ($returnTypeName): bool {
                    return is_a($returnTypeName, $class, true);
                };

                if ($isRelation(MorphTo::class)) {
                    $typedArg->resolver = new ArgResolver(new NestedMorphTo($name));
                    $before[$name] = $typedArg;
                } elseif ($isRelation(BelongsTo::class)) {
                    $before[$name] = $typedArg;
                } elseif ($isRelation(HasMany::class)) {
                    $typedArg->resolver = new ArgResolver(new NestedOneToMany($name));
                }
            } else {
                $regular[$name] = $typedArg;
            }
        }

        return [
            $before,
            $regular,
            $after,
        ];
    }

    public function makeNestedResolvers()
    {
        [$before, $regular, $after] = $this->partitionResolverInputs();

        // Prepare a callback that is passed into the field resolver
        // It should be called with the new root object
        $resolveBeforeResolvers = function ($root) use ($before) {
            /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArg $beforeArg */
            foreach ($before as $beforeArg) {
                // TODO we might continue to automatically wrap the types in ArgResolvers,
                // but we would have to deal with non-null and list types

                ($beforeArg->resolver)($root, $beforeArg->value, $this->context, $this->resolveInfo);
            }
        };

        $resolveAfterResolvers = function ($root) use ($after) {
            /** @var \Nuwave\Lighthouse\Execution\Arguments\TypedArg $afterArg */
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
