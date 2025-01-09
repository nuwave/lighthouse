<?php declare(strict_types=1);

namespace Tests\Utils\Bind;

use Nuwave\Lighthouse\Bind\BindDefinition;
use PHPUnit\Framework\Assert;

/** @template TReturn */
final class SpyCallableClassBinding
{
    private mixed $value = null;

    /** @var \Nuwave\Lighthouse\Bind\BindDefinition<object>|null */
    private ?BindDefinition $definition = null;

    public function __construct(
        /** @var TReturn */
        private mixed $return = null,
    ) {}

    /**
     * @param  \Nuwave\Lighthouse\Bind\BindDefinition<object>  $definition
     *
     * @return TReturn
     */
    public function __invoke(mixed $value, BindDefinition $definition): mixed
    {
        $this->value = $value;
        $this->definition = $definition;

        return $this->return;
    }

    /** @param  \Nuwave\Lighthouse\Bind\BindDefinition<object>  $definition */
    public function assertCalledWith(mixed $value, BindDefinition $definition): void
    {
        Assert::assertSame($value, $this->value);
        Assert::assertEquals($definition, $this->definition);
    }
}
