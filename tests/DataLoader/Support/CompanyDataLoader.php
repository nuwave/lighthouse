<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use GraphQL\Type\Definition\ResolveInfo;

class CompanyDataLoader
{
    /**
     * Pre-resolve data.
     *
     * @param  mixed $parent
     * @param  ResolveInfo|array  $fields
     * @return null
     */
    public function resolve($parent, $info)
    {
        dd($info);
        // TODO: Resolve dataloader.
        return $parent;
    }
}
