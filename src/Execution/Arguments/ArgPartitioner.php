<?php

namespace Nuwave\Lighthouse\Execution\Arguments;

use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\InputObjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nuwave\Lighthouse\Support\Traits\HasResolverArguments;
use ReflectionNamedType;

class ArgPartitioner
{
    use HasResolverArguments;

    public function partitionResolverInputs(): array
    {
        if($this->root instanceof Model){
            $model = new \ReflectionClass($this->root);
        }

        $before = [];
        $regular = [];
        $after = [];

        foreach ($this->args as $name => $value) {
            $argDef = $this->resolveInfo->fieldDefinition->args[$name];
//
//            if (! isset($argDef->config['lighthouse'])) {
//                $regular[$name] = $value;
//                continue;
//            }

            /** @var \Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions $config */
            $config = $argDef->config['lighthouse'];

            if ($config->resolveBefore instanceof ResolveNestedBefore) {
                $before[$name] = $value;
            } elseif ($config->resolveBefore instanceof ResolveNestedAfter) {
                $after[$name] = $value;
            } elseif (isset($model)) {
                if (! $model->hasMethod($name)) {
                    $regular[$name] = $value;
                }

                $relationMethodCandidate = $model->getMethod($name);
                if (! $returnType = $relationMethodCandidate->getReturnType()) {
                    $regular[$name] = $value;
                }

                if (! $returnType instanceof ReflectionNamedType) {
                    $regular[$name] = $value;
                }

                $returnTypeName = $returnType->getName();

                if(is_a($returnTypeName, MorphTo::class, true)){
                    $before[$name] => $value;
                } elseif(){

                } else {
                    $regular[$name] = $value;
                }
            }
        }

        return [
            $before,
            $regular,
            $after,
        ];
    }


}
