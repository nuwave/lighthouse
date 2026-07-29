<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Implement this on ArgResolvers that can run pre-save arg resolvers
 * lifted out of a @nest before persisting the model.
 *
 * Lifted arguments are passed as an ordered list, not as a name-keyed map:
 * field names are unique per input type, not across a whole input tree,
 * so keying them would discard colliding arguments.
 */
interface PreSaveArgumentsAware
{
    /**
     * Return a copy of this resolver that runs the given arguments before saving.
     *
     * @param  list<\Nuwave\Lighthouse\Execution\Arguments\Argument>  $arguments
     *
     * @return static|null null when this resolver can not run pre-save arguments
     */
    public function withPreSaveArguments(array $arguments): ?static;
}
