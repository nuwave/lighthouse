<?php

namespace Nuwave\Lighthouse\Tracing;

use Carbon\Carbon;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;

class Tracing
{
    /**
     * The point in time where the request was initially started.
     *
     * @var \Carbon\Carbon
     */
    protected $requestStart;

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
        $this->requestStart = $startRequest->moment;
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
     * @return mixed[]
     */
    public function handleBuildExtensionsResponse(BuildExtensionsResponse $buildExtensionsResponse): array
    {
        $end = Carbon::now();

        return [
            'tracing' => [
                'version' => 1,
                'startTime' => $this->requestStart->format("Y-m-d\TH:i:s.v\Z"),
                'endTime' => $end->format("Y-m-d\TH:i:s.v\Z"),
                'duration' => $end->diffInSeconds($this->requestStart),
                'execution' => [
                    'resolvers' => $this->resolverTraces,
                ],
            ],
        ];
    }

    /**
     * Record resolver execution time.
     *
     * @param  \GraphQL\Type\Definition\ResolveInfo  $resolveInfo
     * @param  \Carbon\Carbon  $start
     * @param  \Carbon\Carbon  $end
     * @return void
     */
    public function record(ResolveInfo $resolveInfo, Carbon $start, Carbon $end): void
    {
        $this->resolverTraces [] = [
            'path' => $resolveInfo->path,
            'parentType' => $resolveInfo->parentType->name,
            'returnType' => $resolveInfo->returnType->__toString(),
            'fieldName' => $resolveInfo->fieldName,
            'startOffset' => $start->diffInSeconds($this->requestStart),
            'duration' => $start->diffInSeconds($end),
        ];
    }
}
