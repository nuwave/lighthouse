<?php

namespace Tests\Integration\Schema\Directives\Nodes;

use Tests\DBTestCase;

class ModelDirectiveTest extends DBTestCase
{
    /**
     * @test
     */
    public function itGeneratesModelInputTypes()
    {
        // $schema = '
        // input UserWhere {
        //     attr: String!
        //     operator: String
        //     value: String
        // }
        //
        // # union UserWhereAttr = UserWhere | String
        //
        // input UserWhereInInput {
        //     attr: String!
        //     values: [String!]!
        // }
        //
        // input UserWhereBetweenInput {
        //     attr: String!
        //     start: String!
        //     end: String!
        // }
        //
        // input UserInput {
        //     where: UserWhere
        //     orWhere: UserWhere
        //     whereIn: UserWhereInInput
        //     whereNotInt: UserWhereInInput
        //     whereNull: String
        //     whereNotNull: String
        //     whereBetween: UserWhereBetweenInput
        //     whereNotBetween: UserWhereBetweenInput
        //     has: String
        //     doesntHave: String
        // }
        // ';
        $schema = '
        type User @model {
            id: Int!
            first_name: String!
            last_name: String!
            created_at: String!
            updated_at: String!
        }
        ';

        $types = schema()->register($schema);
        $registered = $types->map(function ($type) {
            return $type->name;
        })->toArray();

        $this->assertContains('UserInputType', $registered);
    }
}
