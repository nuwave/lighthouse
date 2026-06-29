<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Resolvers implementing this interface are invoked before $model->save(),
 * allowing them to set foreign keys on the parent model (e.g. BelongsTo).
 *
 * @api
 */
interface PreSaveArgResolver extends ArgResolver {}
