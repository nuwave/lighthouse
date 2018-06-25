<?php

namespace Tests\Unit\Schema\Factories;

use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;
use Tests\TestCase;

class RuleFactoryTest extends TestCase
{
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

        $rules = $this->factory->build($documentAST, [], 'createUser');
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
            createUser(input: UserInput): String
        }');

        $variables = [
            'input' => [
                'email' => 'foo',
            ],
        ];

        $rules = $this->factory->build($documentAST, $variables, 'createUser');
        $this->assertEquals([
            'input.email' => ['required', 'email'],
        ], $rules);
    }
}
