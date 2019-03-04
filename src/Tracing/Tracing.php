<?php

namespace Nuwave\Lighthouse\Tracing;

use Carbon\Carbon;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\ManipulatingAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class Tracing
{
    /**
     * @var \Carbon\Carbon
     */
    protected $requestStart;

    /**
     * Trace entries.
     *
     * @var array[]
     */
    protected $resolverTraces = [];

    /**
     * Set the tracing directive on all fields of the query to enable tracing them.
     *
     * @param  \Nuwave\Lighthouse\Events\ManipulatingAST  $manipulatingAST
     * @return void
     */
    public function handleManipulatingAST(ManipulatingAST $manipulatingAST): void
    {
        ASTHelper::attachDirectiveToObjectTypeFields(
            $manipulatingAST->ast,
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

    /**
     * Format extension output.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $end = Carbon::now();

        return [
            'version' => 1,
            'startTime' => $this->requestStart->format("Y-m-d\TH:i:s.v\Z"),
            'endTime' => $end->format("Y-m-d\TH:i:s.v\Z"),
            'duration' => $end->diffInSeconds($this->requestStart),
            'execution' => [
                'resolvers' => $this->resolverTraces,
            ],
        ];
    }
}
