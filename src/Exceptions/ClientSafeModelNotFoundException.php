<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Exceptions;

use GraphQL\Error\ClientAware;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends ModelNotFoundException<TModel>
 */
class ClientSafeModelNotFoundException extends ModelNotFoundException implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * @param  ModelNotFoundException<TModel>  $laravelException
     *
     * @return self<TModel>
     */
    public static function fromLaravel(ModelNotFoundException $laravelException): self
    {
        return (new static($laravelException->getMessage(), $laravelException->getCode()))
            ->setModel($laravelException->getModel(), $laravelException->getIds());
    }
}
