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
        return 'trace';
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

        return $current->typeDefinitions()
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
     * Record resolver execution time.
     *
     * @param ResolveInfo $info
     * @param Carbon      $start
     * @param Carbon      $end
     */
    public function record(ResolveInfo $info, Carbon $start, Carbon $end)
    {
        $duration = $end->micro - $start->micro;

        $this->resolvers->push([
            'path' => $info->path,
            'parentType' => $info->parentType->name,
            'fieldName' => $info->fieldName,
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
        return $this->resolvers->toArray();
    }
}
