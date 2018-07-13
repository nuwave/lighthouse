<?php

namespace Nuwave\Lighthouse\Schema\Extensions;

use Carbon\Carbon;
use GraphQL\Language\AST\NodeList;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;
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
    public function name()
    {
        return 'tracing';
    }

    /**
     * Manipulate the schema.
     *
     * @param DocumentAST $current
     * @param DocumentAST $original
     *
     * @return DocumentAST
     */
    public function manipulateSchema(DocumentAST $current, DocumentAST $original)
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
     */
    public function requestDidStart(ExtensionRequest $request)
    {
        $this->requestStart = now();

        return;
    }

    /**
     * Record resolver execution time.
     *
     * @param ResolveInfo $info
     * @param Carbon      $start
     */
    public function record(ResolveInfo $info, Carbon $start)
    {
        $end = now();
        $startOffset = ($start->micro - $this->requestStart->micro) * 1000;
        $duration = ($end->micro - $start->micro) * 1000;

        $this->resolvers->push([
            'path' => $info->path,
            'parentType' => $info->parentType->name,
            'returnType' => $this->getReturnType($info->returnType),
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
    public function toArray()
    {
        $end = now();
        $duration = ($end->micro - $this->requestStart->micro) * 1000;

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

    /**
     * Get field return type.
     *
     * @param mixed $type
     *
     * @return string
     */
    protected function getReturnType($type)
    {
        $wrappers = [];

        while (method_exists($type, 'getWrappedType')) {
            if ($type instanceof NonNull) {
                $wrappers[] = '%s!';
            } elseif ($type instanceof ListOfType) {
                $wrappers[] = '[%s]';
            }

            $type = $type->getWrappedType();
        }

        return str_replace('%s', '', collect(array_merge($wrappers, [$type->name]))
            ->reduce(function ($string, $type) {
                return sprintf($string, $type);
            }, '%s'));
    }
}
