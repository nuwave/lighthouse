<?php declare(strict_types=1);

namespace App\GraphQL;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

if (! class_exists(CustomContext::class)) {
    class CustomContext implements GraphQLContext
    {
        public function user(): ?Authenticatable
        {
            return null;
        }

        public function setUser(?Authenticatable $user): void {}

        public function request(): ?Request
        {
            return null;
        }
    }
}
