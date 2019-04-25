<?php

namespace Nuwave\Lighthouse\Tracing;

use Carbon\Carbon;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Execution\ExtensionsResponse;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;

class Tracing
{
    /**
     * The timestamp the request was initially started.
     *
     * @var \Carbon\Carbon
     */
    protected $requestStart;

    /**
     * The precise point in time where the request was initially started.
     *
     * This is either in seconds with microsecond precision (float) or nanoseconds (int).
     *
     * @var float|int
     */
    protected $requestStartPrecise;

    /**
     * Trace entries for a single query execution.
     *
     * Is reset between batches.
     *
     * @var array[]
     */
    protected $resolverTraces = [];

    /**
     * Set the tracing directive on all fields of the query to enable tracing them.
     *
     * @param  \Nuwave\Lighthouse\Events\ManipulateAST  $ManipulateAST
     * @return void
     */
    public function handleManipulateAST(ManipulateAST $ManipulateAST): void
    {
        ASTHelper::attachDirectiveToObjectTypeFields(
            $ManipulateAST->documentAST,
            PartialParser::directive('@tracing')
        );
    }

    /**
     * Handle request start.
     *
     * @param  \Nuwave\Lighthouse\Events\StartRequest  $startRequest
     * @return void
     */
    public function handleStartRequest(StartRequest $startRequest): void
    {
        $this->requestStart = Carbon::now();
        $this->requestStartPrecise = $this->getTime();
    }

    /**
     * Handle batch request start.
     *
     * @param  \Nuwave\Lighthouse\Events\StartExecution  $startExecution
     * @return void
     */
    public function handleStartExecution(StartExecution $startExecution): void
    {
        $this->resolverTraces = [];
    }

    /**
     * Return additional information for the result.
     *
     * @param  \Nuwave\Lighthouse\Events\BuildExtensionsResponse  $buildExtensionsResponse
     * @return \Nuwave\Lighthouse\Execution\ExtensionsResponse
     */
    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): ExtensionsResponse
    {
        $requestEnd = Carbon::now();
        $requestEndPrecise = $this->getTime();

        return new ExtensionsResponse(
            'tracing',
            [
                'version' => 1,
                'startTime' => $this->requestStart->format(Carbon::RFC3339_EXTENDED),
                'endTime' => $requestEnd->format(Carbon::RFC3339_EXTENDED),
                'duration' => $this->diffTimeInNanoseconds($this->requestStartPrecise, $requestEndPrecise),
                'execution' => [
                    'resolvers' => $this->resolverTraces,
                ],
            ]
        );
    }

    /**
     * Record resolver execution time.
     *
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @param  float|int  $start
     * @param  float|int  $end
     * @return void
     */
    public function record(ResolveInfo $resolveInfo, $start, $end): void
    {
        $this->resolverTraces [] = [
            'path' => $resolveInfo->path,
            'parentType' => $resolveInfo->parentType->name,
            'returnType' => $resolveInfo->returnType->__toString(),
            'fieldName' => $resolveInfo->fieldName,
            'startOffset' => $this->diffTimeInNanoseconds($this->requestStartPrecise, $start),
            'duration' => $this->diffTimeInNanoseconds($start, $end),
        ];
    }

    /**
     * Get the system's highest resolution of time possible.
     *
     * This is either in seconds with microsecond precision (float) or nanoseconds (int).
     *
     * @return float|int
     */
    public function getTime()
    {
        return $this->platformSupportsNanoseconds()
            ? hrtime(true)
            : microtime(true);
    }

    /**
     * Diff the time results to each other and convert to nanoseconds if needed.
     *
     * @param  float|int  $start
     * @param  float|int  $end
     * @return int
     */
    protected function diffTimeInNanoseconds($start, $end): int
    {
        if ($this->platformSupportsNanoseconds()) {
            return $end - $start;
        }

        // Difference is in seconds (with microsecond precision)
        // * 1000 to get to milliseconds
        // * 1000 to get to microseconds
        // * 1000 to get to nanoseconds
        return (int) (($end - $start) * 1000 * 1000 * 1000);
    }

    /**
     * Test if the current PHP version has the `hrtime` function available to get a nanosecond precision point in time.
     *
     * @return bool
     */
    protected function platformSupportsNanoseconds(): bool
    {
        return function_exists('hrtime');
    }
}
