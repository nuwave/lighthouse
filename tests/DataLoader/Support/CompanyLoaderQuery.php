<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Tests\Support\Models\Company;

class CompanyLoaderQuery extends CompanyQuery
{
    /**
     * Resolve the query.
     *
     * @param  mixed  $root
     * @param  array  $args
     * @param  mixed  $context
     * @param  ResolveInfo  $info
     * @return mixed
     */
    public function resolve($root, array $args, $context, ResolveInfo $info)
    {
        return Company::find($this->decodeRelayId($args['id']));
    }
}
