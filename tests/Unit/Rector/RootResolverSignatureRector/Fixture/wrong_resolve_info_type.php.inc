<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Users
{
    public function __invoke(null $root, array $args, GraphQLContext $context, BaseResolveInfo $resolveInfo)
    {
        return [];
    }
}
-----
<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

use GraphQL\Type\Definition\ResolveInfo as BaseResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class Users
{
    public function __invoke(null $root, array $args, GraphQLContext $context, \Nuwave\Lighthouse\Execution\ResolveInfo $resolveInfo)
    {
        return [];
    }
}
