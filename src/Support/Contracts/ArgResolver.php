<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

/**
 * Resolve a slice of input arguments during mutation execution.
 *
 * Arg resolvers compose like field resolvers: each handles only its own
 * nested input, and Lighthouse orchestrates traversal of the argument tree.
 *
 * @see \Nuwave\Lighthouse\Support\Contracts\SaveAwareArgResolver
 *
 * @api
 */
interface ArgResolver
{
    /**
     * Handle the given slice of arguments and optionally mutate the root.
     *
     * @param  mixed  $root  the result of the parent resolver, typically an Eloquent Model
     * @param  mixed|\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet|array<\Nuwave\Lighthouse\Execution\Arguments\ArgumentSet>  $value  the slice of arguments that belongs to this nested resolver
     *
     * @return mixed|void|null May return the modified $root
     */
    public function __invoke(mixed $root, mixed $value);
}
