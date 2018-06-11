<?php

namespace Tests\Unit\Schema\Factories;

use GraphQL\Type\Definition\Directive;
use Nuwave\Lighthouse\Schema\AST\PartialParser;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Tests\TestCase;

class DirectiveFactoryTest extends TestCase
{
    public function testClientDirectiveOnSingleType()
    {
        $directiveDefinition = PartialParser::directiveDefinition('
            directive @foo on FIELD
        ');
        
        $directive = DirectiveFactory::toDirective($directiveDefinition);
        
        $this->assertInstanceOf(Directive::class, $directive);
        $this->assertSame('foo', $directive->name);
    }
    
    public function testClientDirectiveOnMultipleTypes()
    {
        $directiveDefinition = PartialParser::directiveDefinition('
            directive @foo on FIELD | SCALAR
        ');
        
        $directive = DirectiveFactory::toDirective($directiveDefinition);
        
        $this->assertInstanceOf(Directive::class, $directive);
        $this->assertCount(2, $directive->locations);
    }
    
    public function testClientDirectiveWithArgument()
    {
        $directiveDefinition = PartialParser::directiveDefinition('
            directive @foo(bar: Int) on OBJECT
        ');
        
        $directive = DirectiveFactory::toDirective($directiveDefinition);
        
        $this->assertInstanceOf(Directive::class, $directive);
        $this->assertSame('bar', $directive->args[0]->name);
    }
    
    public function testClientDirectiveWithDefaultValue()
    {
        $directiveDefinition = PartialParser::directiveDefinition('
            directive @foo(bar: Int = 2) on OBJECT
        ');
        
        $directive = DirectiveFactory::toDirective($directiveDefinition);
        
        $this->assertInstanceOf(Directive::class, $directive);
        $this->assertSame('bar', $directive->args[0]->name);
        // This should probably return 2 as an integer, it does not however
        $this->assertSame('2', $directive->args[0]->defaultValue);
    }
}
