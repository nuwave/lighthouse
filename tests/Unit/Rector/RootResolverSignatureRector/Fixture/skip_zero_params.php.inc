<?php declare(strict_types=1);

namespace App\GraphQL\Queries;

class Health
{
    public function __invoke()
    {
        return 'ok';
    }
}
