<?php


namespace Nuwave\Lighthouse\Schema;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\Directive;
use Nuwave\Lighthouse\Support\Contracts\Directives\ManipulatorDirective;
use Nuwave\Lighthouse\Support\Pipeline;
use Nuwave\Lighthouse\Types\Field;
use Nuwave\Lighthouse\Types\Type;

class Schema
{
    protected $types;

    /**
     * Schema constructor.
     *
     * @param $types
     */
    public function __construct($types)
    {
        $this->types = $types;
    }


    public function types() : Collection
    {
        return $this->types;
    }

    public function type($name) : ?Type
    {
        return $this->types()->firstWhere('name', $name);
    }

    public function runManipulatorDirectives() : Schema
    {
        $schema = $this;
        $this->types()->each(function (Type $type) use (&$schema) {
            // Manipulate our schema from type directives first.
            $typeDirectives = $type->manipulatorDirectives();
            $schema = app(Pipeline::class)
                ->send(new ManipulatorInfo($schema))
                ->through($typeDirectives)
                ->via('handleManipulator')
                ->then(function(ManipulatorInfo $info) {
                    return $info->schema();
                });

            $type->fields()->each(function (Field $field) use (&$schema, $type) {
                $fieldDirectives = $field->manipulatorDirectives();
                $schema = app(Pipeline::class)
                    ->send((new ManipulatorInfo($schema))->setType($type)->setField($field))
                    ->through($fieldDirectives)
                    ->via('handleManipulator')
                    ->then(function(ManipulatorInfo $info) {
                        return $info->schema();
                    });
            });

        });
        return $schema;
    }
}
