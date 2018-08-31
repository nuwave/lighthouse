<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Carbon\Carbon;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Language\AST\FieldDefinitionNode;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;

class TraceExtension extends GraphQLExtension
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
    public function name(): string
    {
        return 'tracing';
    }

    /**
     * Manipulate the schema.
     *
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @throws \Nuwave\Lighthouse\Support\Exceptions\ParseException
     *
     * @return DocumentAST
     */
    public function manipulateSchema(DocumentAST $current, DocumentAST $original): DocumentAST
    {
        $trace = PartialParser::directive('@trace');

        return $current->objectTypeDefinitions()
            ->reduce(function (DocumentAST $document, ObjectTypeDefinitionNode $objectType) use ($trace) {
                if (! data_get($objectType, 'name.value')) {
                    return $document;
                }

                $objectType->fields = new NodeList(collect($objectType->fields)
                    ->map(function (FieldDefinitionNode $field) use ($trace) {
                        $field->directives = $field->directives->merge([$trace]);

                        return $field;
                    })->all());

                $document->setDefinition($objectType);

                return $document;
            }, $current);
    }

    /**
     * Handle request start.
     *
     * @param ExtensionRequest $request
     *
     * @return TraceExtension
     */
    public function requestDidStart(ExtensionRequest $request): TraceExtension
    {
        $this->requestStart = now();

        return $this;
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
    public function toArray(): array
    {
        $end = now();
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
