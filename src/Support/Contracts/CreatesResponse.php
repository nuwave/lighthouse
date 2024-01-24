<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Support\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface CreatesResponse
{
    /**
     * Create a HTTP response from the final result.
     *
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $result
     */
    public function createResponse(array $result): Response;
}
