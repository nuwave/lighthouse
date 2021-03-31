<?php

namespace Tests\Integration\Validation;

use Tests\TestCase;

class RequiredWithoutTest extends TestCase
{
    public function testValidatesRequiredWithoutInArray(): void
    {
        $this->schema = /** @lang GraphQL */ '
            type Query {
                createStuff(input: MyInput!): Int! @field(resolver: "Tests\\\\Utils\\\\Mutations\\\\Foo")
            }

            input MyInput @validator(class: "Tests\\\\Utils\\\\Validators\\\\RequiredWithoutInArrayValidator") {
                items: [ItemInput!]!
            }

            input ItemInput {
                thing: ThingInput!
            }

            input ThingInput {
                an_id: ID
                some_data: SomeDataInput
            }

            input SomeDataInput {
                name: String!
            }
        ';

        $this->graphQL(/** @lang GraphQL */ '
            {
                createStuff(input: {
                    items: [{
                        thing: {
                            some_data: {
                                name: "foobar"
                            }
                        }
                    }]
                })
            }
        ')->assertGraphQLValidationPasses();
    }
}
