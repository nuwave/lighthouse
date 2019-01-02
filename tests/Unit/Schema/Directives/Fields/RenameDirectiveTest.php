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

        $this->schema = "
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

        $this->query($query)->assertJson([
            'data' => [
                'bar' => [
                    'bar' => 'asdf'
                ]
            ]
        ]);
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

        $this->schema = '
        type Query {
            foo: String! @rename
        }
        ';
        $this->query('
        {
            fooBar
        }
        ');
    }
}

class Bar
{
    /**
     * @var string
     */
    public $baz = 'asdf';
}
