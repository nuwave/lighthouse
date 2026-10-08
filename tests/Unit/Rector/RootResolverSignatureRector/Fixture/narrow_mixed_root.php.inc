<?php declare(strict_types=1);

namespace App\GraphQL\Mutations;

class CreateUser
{
    public function __invoke(mixed $root, array $args)
    {
        return [];
    }
}
-----
<?php declare(strict_types=1);

namespace App\GraphQL\Mutations;

class CreateUser
{
    public function __invoke(null $root, array $args)
    {
        return [];
    }
}
