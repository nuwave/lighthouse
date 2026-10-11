<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(null $root, array $arguments)
    {
        $filtered = $arguments['filter'] ?? null;

        return $root ?? $filtered;
    }
}
-----
<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Users
{
    public function __invoke(null $_, array $args)
    {
        $filtered = $args['filter'] ?? null;

        return $_ ?? $filtered;
    }
}
