<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\Arguments\Argument;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

abstract class ManyModelMutationDirective extends ModelMutationDirective
{
    public const NOT_EXACTLY_ONE_ARGUMENT = '@*Many directives must ensure that clients pass exactly one field argument.';

    public const ARGUMENT_NOT_LIST = '@*Many directives must ensure that the single argument value is a list.';

    public const LIST_ITEM_NOT_INPUT_OBJECT = '@*Many directives must ensure that the list items of its single argument value are input objects.';

    public function resolveField(FieldValue $fieldValue): callable
    {
        $modelClass = $this->getModelClass();

        return function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($modelClass): array {
            $model = new $modelClass();

            $arguments = $resolveInfo->argumentSet
                ->arguments;
            if (count($arguments) !== 1) {
                throw new DefinitionException(self::NOT_EXACTLY_ONE_ARGUMENT);
            }

            $argument = Arr::first($arguments);
            assert($argument instanceof Argument, 'proven because the argument count was 1');

            $inputs = $argument->value;
            if (! is_array($inputs)) {
                throw new DefinitionException(self::ARGUMENT_NOT_LIST);
            }

            return $this->transactionalMutations->execute(
                function () use ($model, $inputs): array {
                    $results = [];

                    foreach ($inputs as $input) {
                        if (! $input instanceof ArgumentSet) {
                            throw new DefinitionException(self::LIST_ITEM_NOT_INPUT_OBJECT);
                        }

                        $mutated = $this->executeMutation($model, $input);
                        assert($mutated instanceof Model);

                        $results[] = $mutated->refresh();
                    }

                    return $results;
                },
                $model->getConnectionName(),
            );
        };
    }
}
