<?php

namespace Tests\Unit\Schema\Types\Scalars;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use Illuminate\Http\UploadedFile;
use Nuwave\Lighthouse\Schema\Types\Scalars\Upload;
use Tests\TestCase;

class UploadTest extends TestCase
{
    public function testThrowsIfSerializing(): void
    {
        $this->expectException(InvariantViolation::class);

        (new Upload)->serialize('');
    }

    public function testThrowsIfParsingLiteral(): void
    {
        $this->expectException(Error::class);

        (new Upload)->parseLiteral(''); // @phpstan-ignore-line Error is on purpose
    }

    public function testThrowsIfParsingValueNotFile(): void
    {
        $this->expectException(Error::class);

        (new Upload)->parseValue('not a file');
    }

    public function testParsesValidFiles(): void
    {
        $value = UploadedFile::fake()
            ->create('my-file.jpg', 500);
        $parsedValue = (new Upload)->parseValue($value);

        $this->assertEquals($value, $parsedValue);
    }
}
