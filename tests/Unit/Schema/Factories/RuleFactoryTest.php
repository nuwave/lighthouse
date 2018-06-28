<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;

class RuleFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function itCanGenerateRulesForMutationArguments()
    {
        $documentAST = ASTBuilder::generate('
        type Mutation {
            createUser(email: String @rules(apply: ["required", "email"])): String
        }');

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            [],
            'createUser'
        );

        $this->assertEquals([
            'email' => ['required', 'email'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input UserInput {
            email: String @rules(apply: ["required", "email"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'email' => 'foo',
            ],
        ];

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            $variables,
            'createUser'
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForNestedInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input AddressInput {
            street: String @rules(apply: ["required"])
            primary: Boolean @rules(apply: ["required"])
        }
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            address: AddressInput @rules(apply: ["required"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'address' => [
                    'street' => 'bar',
                ],
            ],
        ];

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            $variables,
            'createUser'
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.address' => ['required'],
            'input.address.street' => ['required'],
            'input.address.primary' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForNestedInputArgumentLists()
    {
        $documentAST = ASTBuilder::generate('
        input AddressInput {
            street: String @rules(apply: ["required"])
            primary: Boolean @rules(apply: ["required"])
        }
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            address: [AddressInput] @rules(apply: ["required"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'address' => [
                    'street' => 'bar',
                ],
            ],
        ];

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            $variables,
            'createUser'
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.address' => ['required'],
            'input.address.*.street' => ['required'],
            'input.address.*.primary' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForSelfReferencingInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input Setting {
            option: String @rules(apply: ["required"])
            value: String @rules(apply: ["required"])
            setting: Setting
        }
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            settings: [Setting] @rules(apply: ["required"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'settings' => [
                    [
                        'option' => 'foo',
                        'value' => 'bar',
                        'setting' => [
                            'option' => 'bar',
                            'value' => 'baz',
                        ],
                    ],
                ],
            ],
        ];

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            $variables,
            'createUser'
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.settings' => ['required'],
            'input.settings.*.option' => ['required'],
            'input.settings.*.value' => ['required'],
            'input.settings.*.setting.option' => ['required'],
            'input.settings.*.setting.value' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itAlwaysGeneratesRequiredRules()
    {
        $documentAST = ASTBuilder::generate('
        type Mutation {
            createFoo(required: String @rules(apply: ["required"])): String
        }');

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            [],
            'createFoo'
        );

        $this->assertEquals([
            'required' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itAlwaysGeneratesRulesForRequiredNestedInputs()
    {
        $documentAST = ASTBuilder::generate('
        input FooInput {
            required: String @rules(apply: ["required"])
        }
        type Mutation {
            createFoo(
                requiredSDL: FooInput!
                requiredRules: FooInput @rules(apply: ["required"])
                requiredBoth: FooInput! @rules(apply: ["required"])
            ): String
        }');

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            [],
            'createFoo'
        );

        $this->assertEquals([
            'requiredRules' => ['required'],
            'requiredSDL.required' => ['required'],
            'requiredBoth' => ['required'],
            'requiredBoth.required' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itGeneratesRequiredNestedRulesWhenParentIsGiven()
    {
        $documentAST = ASTBuilder::generate('
        input FooInput {
            self: FooInput
            required: String @rules(apply: ["required"])
        }
        type Mutation {
            createFoo(input: FooInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'self' => [],
            ],
        ];

        $rules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            $variables,
            'createFoo'
        );

        $this->assertEquals([
            'input' => ['required'],
            'input.required' => ['required'],
            'input.self.required' => ['required'],
        ], $rules);
    }
    
    /**
     * @test
     */
    public function itHandlesNestedRulesOnQueriesAndMutations()
    {
        $documentAST = ASTBuilder::generate('
        input Foo {
            bar: Int @rules(apply: ["required"])
        }
        type Mutation {
            foo(input: Foo): String
        }
        type Query {
            foo(input: Foo): String
        }
        ');
    
        $variables = [
            'input' => [
                'bar' => 42,
            ],
        ];
    
        $mutationRules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Mutation'),
            $variables,
            'foo'
        );
        
        $queryRules = (new RuleFactory())->build(
            $documentAST,
            $documentAST->objectTypeDefinition('Query'),
            $variables,
            'foo'
        );
        
        $this->assertSame($mutationRules, $queryRules);
    }
}
