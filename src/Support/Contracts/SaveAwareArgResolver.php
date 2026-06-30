<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implement this on ArgResolver directives that need to control whether they
 * run before or after the parent model is saved during mutation execution.
 *
 * @api
 */
interface SaveAwareArgResolver extends ArgResolver
{
    /**
     * Should this resolver run before the parent model is persisted?
     *
     * When true, the resolver is invoked before $model->save(), allowing it
     * to set attributes or foreign keys on the model.
     * When false, the resolver runs after the model is saved (the default
     * for any ArgResolver that does not implement this interface).
     *
     * Only consulted when the root is a Model inside SaveModel's orchestration.
     * In non-Model contexts (e.g. @nest), this method is not called and the resolver runs in the default post-save position.
     */
    public function runBeforeSave(Model $model): bool;
}
