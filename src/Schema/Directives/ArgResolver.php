<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\DatabaseManager;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Execution\MutationExecutor;
use Nuwave\Lighthouse\Support\Contracts\FieldResolver;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Execution\Arguments\AfterResolver;
use Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions;

class ArgResolver extends BaseDirective implements FieldResolver
{
    /**
     * @var \Illuminate\Database\DatabaseManager
     */
    protected $databaseManager;

    /**
     * @param  \Illuminate\Database\DatabaseManager  $databaseManager
     * @return void
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    /**
     * Name of the directive.
     *
     * @return string
     */
    public function name(): string
    {
        return 'create';
    }

    /**
     * Resolve the field directive.
     *
     * @param  \Nuwave\Lighthouse\Schema\Values\FieldValue  $fieldValue
     * @return \Nuwave\Lighthouse\Schema\Values\FieldValue
     */
    public function resolveField(FieldValue $fieldValue): FieldValue
    {
        return $fieldValue->setResolver(
            function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Model {
                [$afterResolvers, $regular] = $this->partitionArguments($args, $resolveInfo->fieldDefinition);

                $modelClassName = $this->getModelClass();
                /** @var \Illuminate\Database\Eloquent\Model $model */
                $model = new $modelClassName($regular);
                $model->save();

                /** @var AfterResolver $afterResolver */
                foreach ($afterResolvers as $afterResolver) {
                    $afterResolver->resolve($model, $args, $context);
                }
                $executeMutation = function () use ($model, $args): Model {
                    return MutationExecutor::executeCreate($model, new Collection($args))->refresh();
                };

                return config('lighthouse.transactional_mutations', true)
                    ? $this->databaseManager->connection($model->getConnectionName())->transaction($executeMutation)
                    : $executeMutation();
            }
        );
    }

    protected function partitionResolverInputs(array $args, $definitions): array
    {
        return $this->partitionArguments($args, $definitions, function (ArgumentExtensions $argumentExtensions) {
            return $argumentExtensions->resolver instanceof AfterResolver;
        });
    }

    /**
     * @param array $args
     * @param \GraphQL\Type\Definition\FieldArgument[]|\GraphQL\Type\Definition\InputObjectField[] $definitions
     */
    protected function partitionArguments(array $args, $definitions, callable $isSpecial)
    {
        $special = [];
        $regular = [];

        foreach ($args as $name => $value) {
            $argDef = $definitions['name'];
            /** @var \Nuwave\Lighthouse\Schema\Extensions\ArgumentExtensions $config */
            $config = $argDef->config['lighthouse'];
            $isSpecial($config)
                ? $special[$name] = $value
                : $regular[$name] = $value;
        }

        return [
            $special,
            $regular,
        ];
    }

    public static function defaultArgResolver($root, $value)
    {
        $root->value;
    }
}
