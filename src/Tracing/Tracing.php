<?php

namespace Nuwave\Lighthouse\Tracing;

use Carbon\Carbon;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Events\StartBatch;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
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
     * @param  \Nuwave\Lighthouse\Schema\AST\DocumentAST  $documentAST
     * @return \Nuwave\Lighthouse\Schema\AST\DocumentAST
     */
    public function manipulateSchema(DocumentAST $documentAST): DocumentAST
    {
        return ASTHelper::attachDirectiveToObjectTypeFields(
            $documentAST,
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
     * @param  \Nuwave\Lighthouse\Events\StartBatch  $startBatch
     * @return void
     */
    public function handleStartBatch(StartBatch $startBatch): void
    {
        $this->resolverTraces = [];
    }

    /**
     * Record resolver execution time.
     *
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     * @param  \Carbon\Carbon  $start
     * @param  \Carbon\Carbon  $end
     * @return void
     */
    public function record(ResolveInfo $info, Carbon $start, Carbon $end): void
    {
        $startOffset = abs(($start->micro - $this->requestStart->micro) * 1000);
        $duration = abs(($end->micro - $start->micro) * 1000);

        $this->resolverTraces []= [
            'path' => $info->path,
            'parentType' => $info->parentType->name,
            'returnType' => $info->returnType->__toString(),
            'fieldName' => $info->fieldName,
            'startOffset' => $startOffset,
            'duration' => $duration,
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
        $duration = abs(($end->micro - $this->requestStart->micro) * 1000);

        return [
            'version' => 1,
            'startTime' => $this->requestStart->format("Y-m-d\TH:i:s.v\Z"),
            'endTime' => $end->format("Y-m-d\TH:i:s.v\Z"),
            'duration' => $duration,
            'execution' => [
                'resolvers' => $this->resolverTraces,
            ],
        ];
    }
}
