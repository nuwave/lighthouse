<?php

namespace Tests\Unit\Execution\Utils;

use Nuwave\Lighthouse\Execution\Utils\ModelKey;
use Tests\TestCase;
use Tests\Utils\Models\Product;
use Tests\Utils\Models\User;

class ModelKeyTest extends TestCase
{
    public function testModelSingleKey(): void
    {
        $user = new User();
        $user->id = 2;

        $this->assertSame('Tests\Utils\Models\User:2', ModelKey::build($user));
    }

    public function testModelCompositeKey(): void
    {
        $product = new Product();
        $product->barcode = '123';
        $product->uuid = 'abc';

        $this->assertSame('Tests\Utils\Models\Product:123:abc', ModelKey::build($product));
    }
}
