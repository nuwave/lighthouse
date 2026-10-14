<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Nuwave\Lighthouse\Schema\Values\FieldValue;

/**
 * Provide a resolver for a subscription field in case no resolver directive is defined.
 *
 * @api
 */
interface ProvidesSubscriptionResolver
{
    /** This function is expected to call @see \Nuwave\Lighthouse\Subscriptions\SubscriptionRegistry::register(). */
    public function provideSubscriptionResolver(FieldValue $fieldValue): \Closure;
}
