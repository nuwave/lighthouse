<?php

namespace Tests\Unit\Schema\AST;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\PartialParser;

class ASTHelperTest extends TestCase
{
    /**
     * @test
     */
    public function itCanMergeUniqueNodeLists()
    {
        $objectType1 = PartialParser::objectTypeDefinition('
        type User {
            first_name: String
            email: String
        }');

        $objectType2 = PartialParser::objectTypeDefinition('
        type User {
            first_name: String @foo
            last_name: String
        }');

        $objectType1->fields = ASTHelper::mergeUniqueNodeList(
            $objectType1->fields,
            $objectType2->fields
        );

        $this->assertCount(3, $objectType1->fields);

        $firstNameField = collect($objectType1->fields)->first(function ($field) {
            return 'first_name' === $field->name->value;
        });

        $this->assertCount(1, $firstNameField->directives);
    }
}
