<?php

namespace Tests\Utils;

class ThrowingMiddleware
{
    public function handle(): void
    {
        throw new \Exception('foo');
    }
}
