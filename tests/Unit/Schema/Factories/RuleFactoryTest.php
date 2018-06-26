<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;

class RuleFactoryTest extends TestCase
{
    /**
     * @var RuleFactory
     */
    protected $factory;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->factory = new RuleFactory();
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForMutationArguments()
    {
        $documentAST = ASTBuilder::generate('
        type Mutation {
            createUser(email: String @rules(apply: ["required", "email"])): String
        }');

        $rules = $this->factory->build($documentAST, [], 'createUser', 'Mutation');
        $this->assertEquals([
            'email' => ['required', 'email'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForQueryArguments()
    {
        $documentAST = ASTBuilder::generate('
        type Query {
            findUser(email: String @rules(apply: ["required", "email"])): String
        }');

        $rules = $this->factory->build($documentAST, [], 'findUser', 'Query');
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

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
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

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
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

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
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

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
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
    public function itGeneratesOnlyMinimalNeededRules()
    {
        $documentAST = ASTBuilder::generate('
        input FooInput {
            self: FooInput
            email: String @rules(apply: ["email"])
        }
        type Mutation {
            createFoo(input: FooInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'self' => [
                    'self' => [
                        'email' => 'asdf'
                    ],
                ],
            ],
        ];

        $rules = $this->factory->build($documentAST, $variables, 'createFoo');
        $this->assertEquals([
            'input' => ['required'],
            'input.self.self.email' => ['email'],
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
        
        $rules = $this->factory->build($documentAST, [], 'createFoo');
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

        $rules = $this->factory->build($documentAST, [], 'createFoo');
        $this->assertEquals([
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
                'self' => []
            ]
        ];

        $rules = $this->factory->build($documentAST, $variables, 'createFoo');
        $this->assertEquals([
            'input' => ['required'],
            'input.required' => ['required'],
            'input.self.required' => ['required'],
        ], $rules);
    }
}
