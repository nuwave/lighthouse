<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implement this on ArgResolver directives that need to control whether they
 * run before or after the parent model is saved during mutation execution.
 *
 * When `runBeforeSave()` returns true, `__invoke()` receives null as `$value`
 * if the client sends null for a nullable input field. Guard accordingly.
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
     * Only consulted when the root is a Model.
     * In non-Model contexts, this method is not called and the resolver executes normally.
     *
     * Implementations must base the decision on the model class and its relations,
     * not on instance state — the model may not yet be hydrated when this is called.
     */
    public function runBeforeSave(Model $model): bool;
}
