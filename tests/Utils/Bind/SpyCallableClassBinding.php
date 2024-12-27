<?php

declare(strict_types=1);

namespace Tests\Utils\Bind;

use Nuwave\Lighthouse\Bind\BindDefinition;
use PHPUnit\Framework\Assert;

/**
 * @template TReturn
 */
final class SpyCallableClassBinding
{
    private mixed $value = null;
    private ?BindDefinition $definition = null;

    /**
     * @param TReturn $return
     */
    public function __construct(
        private mixed $return = null,
    ) {}

    /**
     * @return TReturn
     */
    public function __invoke(mixed $value, BindDefinition $definition): mixed
    {
        $this->value = $value;
        $this->definition = $definition;

        return $this->return;
    }

    public function assertCalledWith(mixed $value, BindDefinition $definition): void
    {
        Assert::assertSame($value, $this->value);
        Assert::assertEquals($definition, $this->definition);
    }
}
