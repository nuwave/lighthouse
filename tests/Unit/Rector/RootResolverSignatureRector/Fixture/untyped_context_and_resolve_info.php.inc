<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(null $root, array $args, $context, $resolveInfo)
    {
        return [];
    }
}
-----
<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Execution\ResolveInfo;

class Users
{
    public function __invoke(null $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return [];
    }
}
