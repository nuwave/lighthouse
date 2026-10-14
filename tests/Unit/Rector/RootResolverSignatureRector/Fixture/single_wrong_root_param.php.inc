<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(string $root)
    {
        return [];
    }
}
-----
<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Users
{
    public function __invoke()
    {
        return [];
    }
}
