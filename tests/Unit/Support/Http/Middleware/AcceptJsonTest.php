<?php declare(strict_types=1);

namespace Tests\Unit\Support\Http\Middleware;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Support\Http\Middleware\AcceptJson;
use Tests\TestCase;

final class AcceptJsonTest extends TestCase
{
    public function testSetsHeader(): void
    {
        $acceptJson = new AcceptJson();

        $request = $acceptJson->handle(
            new Request(),
            function (Request $request): Request {
                return $request;
            }
        );

        $this->assertSame(
            AcceptJson::APPLICATION_JSON,
            $request->headers->get(AcceptJson::ACCEPT)
        );
    }
}
