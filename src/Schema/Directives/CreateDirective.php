<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Utils;
use Nuwave\Lighthouse\Execution\Arguments\SaveModel;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\Arguments\ResolveNested;

class CreateDirective extends MutationExecutorDirective
{
    public function name(): string
    {
        return 'create';
    }

    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'SDL'
"""
Create a new Eloquent model with the given arguments.
"""
directive @create(  
  """
  Specify the class name of the model to use.
  This is only needed when the default model resolution does not work.
  """
  model: String
) on FIELD_DEFINITION
SDL;
    }

    /**
     * Execute a mutation on a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *         An empty instance of the model that should be mutated.
     * @param  \Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet[]  $args
     *         The user given input arguments for mutating this model.
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Model[]
     */
    public function __invoke($model, $args)
    {
        if ($relationName = $this->directiveArgValue('relation')) {
            /** @var \Illuminate\Database\Eloquent\Relations\Relation $relation */
            $relation = $model->{$relationName}();
            $model = $relation->make();
        }

        $saveModel = new ResolveNested(new SaveModel());

        return Utils::applyEach(
            static function (ArgumentSet $argumentSet) use ($saveModel, $model) {
                return $saveModel($model, $argumentSet);
            },
            $args
        );
    }
}
