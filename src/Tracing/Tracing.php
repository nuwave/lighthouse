<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Tracing;

use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Execution\ResolveInfo;

interface Tracing
{
    public function handleStartRequest(StartRequest $startRequest): void;

    public function handleStartExecution(StartExecution $startExecution): void;

    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ?ExtensionsResponse;

    /** Record resolver execution time. */
    public function record(ResolveInfo $resolveInfo, float|int $start, float|int $end): void;

    /**
     * Get the system's highest resolution of time possible.
     *
     * This is either in seconds with microsecond precision (float) or nanoseconds (int).
     */
    public function timestamp(): float|int;
}
