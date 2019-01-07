<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Carbon\Carbon;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class TracingExtension extends GraphQLExtension
{
    /**
     * @var \Carbon\Carbon
     */
    protected $requestStart;

    /**
     * Trace entries.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $resolvers;

    /**
     * Create instance of trace extension.
     */
    public function __construct()
    {
        $this->resolvers = collect();
    }

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
     * @param  DocumentAST $documentAST
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
     * @param  ExtensionRequest $request
     *
     * @return $this
     */
    public function requestDidStart(ExtensionRequest $request): self
    {
        $this->requestStart = Carbon::now();

        return $this;
    }

    /**
     * Handle batch request start.
     *
     * @param  int $index
     *
     * @return void
     */
    public function batchedQueryDidStart(int $index): void
    {
        $this->resolvers = collect();
    }

    /**
     * Record resolver execution time.
     *
     * @param  ResolveInfo $info
     * @param  Carbon      $start
     * @param  Carbon      $end
     *
     * @return void
     */
    public function record(ResolveInfo $info, Carbon $start, Carbon $end): void
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
