<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(null $root, array $arguments)
    {
        return [];
    }
}
-----
<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(null $_, array $args)
    {
        return [];
    }
}
