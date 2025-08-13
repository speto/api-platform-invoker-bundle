<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\Tests\Fixtures\ValueObjects;

use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\UriVarConstructor;

#[UriVarConstructor('create')]
final readonly class UserId
{
    private function __construct(
        public int $value
    ) {
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public static function create(int|string $value): self
    {
        return new self((int) $value);
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
