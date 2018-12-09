<?php

namespace Tests\Unit\Schema\Directives\Fields;

use Tests\TestCase;
use Nuwave\Lighthouse\Exceptions\DirectiveException;

class RenameDirectiveTest extends TestCase
{
    /**
     * @test
     */
    public function itCanRenameAField()
    {
        $resolver = addslashes(self::class).'@resolve';

        $schema = "
        type Query {
            bar: Bar @field(resolver: \"{$resolver}\")
        }
        
        type Bar {
            bar: String! @rename(attribute: \"baz\")
        }
        ";
        $query = '
        {
            bar {
                bar
            }
        }
        ';
        $result = $this->execute($schema, $query);

        $this->assertSame('asdf', \Illuminate\Support\Arr::get($result, 'data.bar.bar'));
    }

    public function resolve()
    {
        return new Bar;
    }

    /**
     * @test
     */
    public function itThrowsAnExceptionIfNoAttributeDefined()
    {
        $this->expectException(DirectiveException::class);
        $this->execute('
        type Query {
            foo: String! @rename
        }
        ', '
        {
            fooBar
        }
        ');
    }
}

class Bar
{
    public $baz = 'asdf';
}
