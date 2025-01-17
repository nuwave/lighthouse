<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Execution\Arguments;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Contracts\ArgResolver;

class UpsertModel implements ArgResolver
{
    /** @var callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver */
    protected $previous;

    /** @param  callable|\Nuwave\Lighthouse\Support\Contracts\ArgResolver  $previous */
    public function __construct(callable $previous)
    {
        $this->previous = $previous;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  ArgumentSet  $args
     */
    public function __invoke($model, $args): mixed
    {
        // TODO consider Laravel native ->upsert(), available from 8.10

        $id = $this->retrieveID($model, $args);
        if ($id) {
            $existingModel = $model->newQuery()
                ->find($id);

            if ($existingModel !== null) {
                $model = $existingModel;
            }
        }

        return ($this->previous)($model, $args);
    }

    /** @return mixed The value of the ID or null */
    protected function retrieveID(Model $model, ArgumentSet $args)
    {
        foreach (['id', $model->getKeyName()] as $key) {
            if (! isset($args->arguments[$key])) {
                continue;
            }

            $id = $args->arguments[$key]->value;
            if ($id) {
                return $id;
            }

            // Prevent passing along empty IDs that would be filled into the model
            unset($args->arguments[$key]);
        }

        return null;
    }
}
