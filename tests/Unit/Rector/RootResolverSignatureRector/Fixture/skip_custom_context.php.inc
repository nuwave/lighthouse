<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\GraphQL\CustomContext;
use Nuwave\Lighthouse\Execution\ResolveInfo;

class WithCustomContext
{
    public function __invoke(null $root, array $args, CustomContext $context, ResolveInfo $resolveInfo)
    {
        return [];
    }
}
