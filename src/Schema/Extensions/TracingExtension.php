<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Carbon\Carbon;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class TracingExtension extends GraphQLExtension
{
    /**
     * @var Carbon
     */
    protected $requestStart;

    /**
     * Trace entries.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $resolvers;

    /**
     * Extension name.
     *
     * @return string
     */
    public static function name(): string
    {
        return 'tracing';
    }

    /**
     * Set the tracing directive on all fields of the query to enable tracing them.
     *
     * @param DocumentAST $documentAST
     *
     * @throws \Nuwave\Lighthouse\Exceptions\ParseException
     *
     * @return DocumentAST
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
     * @param GraphQLRequest $request
     */
    public function start(GraphQLRequest $request)
    {
        // Keep this value the same in case we are dealing
        // with a batched request and this is called multiple times
        if (! $this->requestStart) {
            $this->requestStart = Carbon::now();
        }

        $this->resolvers = collect();
    }

    /**
     * Record resolver execution time.
     *
     * @param ResolveInfo $info
     * @param Carbon      $start
     * @param Carbon      $end
     */
    public function record(ResolveInfo $info, Carbon $start, Carbon $end)
    {
        $startOffset = abs(($start->micro - $this->requestStart->micro) * 1000);
        $duration = abs(($end->micro - $start->micro) * 1000);

        $this->resolvers->push([
            'path' => $info->path,
            'parentType' => $info->parentType->name,
            'returnType' => $info->returnType->__toString(),
            'fieldName' => $info->fieldName,
            'startOffset' => $startOffset,
            'duration' => $duration,
        ]);
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
                'resolvers' => $this->resolvers->toArray(),
            ],
        ];
    }
}
