<?php


namespace Nuwave\Lighthouse\Schema;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\Directives\ManipulatorDirective;
use Nuwave\Lighthouse\Support\Contracts\Directives\NodeManipulator;
use Nuwave\Lighthouse\Support\Contracts\SchemaBuilder as SchemaBuilderInterface;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\Type;

abstract class SchemaBuilder implements SchemaBuilderInterface
{
    /** @var DirectiveRegistry */
    protected $directiveRegistry;

    /** @var Collection */
    protected $types;

    /** @var Schema */
    protected $schema;

    /**
     * SchemaBuilder constructor.
     *
     * @param DirectiveRegistry $directiveRegistry
     */
    public function __construct(DirectiveRegistry $directiveRegistry)
    {
        $this->directiveRegistry = $directiveRegistry;
        $this->types = collect();
    }


    /**
     * Generates the schema from type system language.
     *
     * It should generate the schema with extension types and run our
     * manipulators on the types.
     *
     * @param string $typeLanguage
     * @return Schema
     */
    public function buildFromTypeLanguage(string $typeLanguage): Schema
    {
        // First parse the type language to a document node
        $documentNode = $this->parseToDocumentNode($typeLanguage);

        // Then get all the types.
        $types = $this->getTypesFromDocumentNode($documentNode)->values();

        // Then get all the types from extension types.
        $types = $types->merge(
            $this->getExtensionTypesFromDocumentNode($documentNode)->values()
        );

        // Then we run the manipulator on each type.
        $types = $this->manipulate($types);

        // Afterwards we merge the types which are the same together.
        $types = $this->mergeTypes($types);

        // At last we create the schema object from the types.
        $schema = $this->createSchema($types);

        $this->schema = $schema;

        return $schema;
    }

    public function addType(Type $type): SchemaBuilderInterface
    {
        $this->types->push($type);
        return $this;
    }


    /**
     * Converts a type language into a document node.
     *
     * @param string $typeLanguage
     * @return mixed
     */
    public abstract function parseToDocumentNode(string $typeLanguage);

    /**
     * Should only return the normal types.
     * It should ignore extension types totally.
     *
     * @param $documentNode
     * @return Collection
     */
    public abstract function getTypesFromDocumentNode($documentNode) : Collection;


    /**
     * Should return extension types.
     * It should not merge the extension types together if they are the same type.
     *
     * @param $documentNode
     * @return Collection
     */
    public abstract function getExtensionTypesFromDocumentNode($documentNode) : Collection;

    /**
     * Merges types with the same name together.
     *
     * @param Collection $types
     * @return Collection
     */
    public function mergeTypes(Collection $types) : Collection
    {
        // Map all types which has the same name to one name.
        $types = $types->groupBy('name')->map(function (Collection $types) {
            // If there only is one type in the group
            // then return that type.
            if($types->count() == 1) {
                return $types->first();
            }

            // Merge all the fields of the same type to one type.
            $mergedType = $types->reduce(function ($carry, Type $item) {
                if(is_null($carry)) {
                    return $item;
                }
                // Add all the fields to the item.
                $item->fields()->each(function (Field $field) use ($carry) {
                    $carry->addField($field);
                });
                return $carry;
            });

            return $mergedType;
        });

        return $types;
    }

    /**
     * Create a schema from a collection of types.
     *
     * @param Collection $types
     * @return Schema
     */
    public function createSchema(Collection $types) : Schema
    {
        return new Schema($types);
    }

    /**
     * Run the manipulator on each type.
     *
     * @param Collection $types
     * @return Collection
     */
    public function manipulate(Collection $types) : Collection
    {
        // Resolve the manipulators
        $types = $types->each(function (Type $type) use (&$types){
            // Get all directives which are node manipulators
            $manipulators = $this->directiveRegistry->getFromDirectives($type->directives())
                ->filter(function (Directive $directive) {
                    return $directive instanceof NodeManipulator;
            });

            // Resolve the manipulators on the node.
            /** @var ManipulatorInfo $manipulatorInfo */
            $manipulatorInfo = app(Pipeline::class)
                ->send(new ManipulatorInfo($types, $type))
                ->through($manipulators)
                ->via('manipulateNode')
                ->then(function($value) {
                    return $value;
                });

            $types = $manipulatorInfo->getTypes();

        });
        return $types;
    }
}