<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class OneModelMutationDirective extends ModelMutationDirective
{
    public function resolveField(FieldValue $fieldValue): callable
    {
        $modelClass = $this->getModelClass();

        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelClass): Model {
            $model = new $modelClass();

            return $this->transactionalMutations->execute(
                function () use ($model, $resolveInfo): Model {
                    $mutated = $this->executeMutation($model, $resolveInfo->argumentSet);
                    assert($mutated instanceof Model);

                    return $mutated->refresh();
                },
                $model->getConnectionName(),
            );
        };
    }
}
